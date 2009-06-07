<?php

// Prevent direct access to this file
if ( ! defined('MINT')) header('Location:/');

/**
 * Pepper class name to install
 * @var string
 */
$installPepper = 'BK_SCMPurchases';

/**
 * SCM Purchases Class
 *
 * This pepper enables you to track purchases made through
 * ExpressionEngine's Simple Commerce module right from Mint
 *
 * @package   SC Purchases
 * @author    Brandon Kelly <me@brandon-kelly.com>
 * @copyright Copyright (c) 2009 Brandon Kelly
 * @license   http://creativecommons.org/licenses/by-sa/3.0/ Attribution-Share Alike 3.0 Unported
 */
class BK_SCMPurchases extends Pepper {

	var $version = 001;

	var $info = array(
		'pepperName'    => 'SCM Purchases',
		'pepperUrl'     => 'http://brandon-kelly.com/',
		'pepperDesc'    => 'Track purchases made through ExpressionEngine’s Simple Commerce module.',
		'developerName' => 'Brandon Kelly',
		'developerUrl'  => 'http://brandon-kelly.com'
	);

	var $panes = array(
		'Purchases' => array(
			'Past Day',
			'Past Week',
			'Past Month',
			'Past Year'
		)
	);

	var $oddPanes = array(
		'Visits'
	);

	var $prefs = array(
		'systemFolderPath' => '../system/'
	);

	var $hasCrush = false;

	/**
	 * Check Compatibility
	 */
	function isCompatible()
	{
		return ($this->Mint->version < 200)
		?	array(
				'isCompatible' => false,
				'explanation'  => '<p>This Pepper requires Mint 2.00. Mint 2, a paid upgrade from Mint 1.x, is available at <a href="http://www.haveamint.com/">haveamint.com</a>.</p>'
			)
		:	array(
				'isCompatible'	=> true,
			);
	}

	/**
	 * Display Preferences
	 *
	 * @return string  The preferences' HTML
	 */
	function onDisplayPreferences()
	{
		$preferences['System Folder Path'] = <<<HERE
<table>
	<tr>
		<td><label>Enter the path to your System folder so that Mint can connect to your ExpressionEngine database.</label></td>
	</tr>
	<tr>
		<td><span><input id="systemFolderPath" name="systemFolderPath" type="text" value="{$this->prefs['systemFolderPath']}"/></span></td>
	</tr>
</table>
HERE;
		return $preferences;
	}

	/**
	 * Save Preferences
	 */
	function onSavePreferences()
	{
		if (isset($_POST['systemFolderPath']))
		{
			$this->prefs['systemFolderPath'] = $_POST['systemFolderPath'];

			// force trailing slash
			if (substr($this->prefs['systemFolderPath'], -1) != '/')
			{
				$this->prefs['systemFolderPath'] .= '/';
			}
		}
	}

	/**
	 * Display Pane
	 *
	 * @param  string  $pane  The pane's name
	 * @param  string  $tab   The tab's name
	 * @return string  The pane's HTML
	 */
	function onDisplay($pane, $tab, $column = '', $sort = '')
	{
		$html = '';

		// open config.php
		if ( ! defined('EXT')) define('EXT', NULL);
		if ( ! include_once($this->prefs['systemFolderPath'] . 'config.php'))
		{
			return 'Your System folder couldn’t be found. Make sure your System Folder Path preference is correct.';
		}
		$this->conf = $conf;

		// create a new db connection
		if (($this->conn = mysql_connect($this->conf['db_hostname'], $this->conf['db_username'], $this->conf['db_password'], TRUE)) === FALSE)
		{
			return 'Could not connect to ExpressionEngine’s database.';
		}
		if (mysql_select_db($this->conf['db_name'], $this->conn) === FALSE)
		{
			mysql_close($this->conn);
			return 'Could not connect to ExpressionEngine’s database.';
		}

		// add item filters
		$filters = array(
			'All' => 0
		);
		$query = mysql_query('SELECT sci.item_id, wt.title'
		                     . ' FROM '.$this->conf['db_prefix'].'_simple_commerce_items sci, '.$this->conf['db_prefix'].'_weblog_titles wt'
		                     . ' WHERE sci.entry_id = wt.entry_id');
		while($row = mysql_fetch_assoc($query))
		{
			$filters[$row['title']] = $row['item_id'];
		}
		$html .= $this->generateFilterList($tab, $filters, $this->panes['Purchases']);

		switch($pane)
		{
			case 'Purchases':
				switch($tab)
				{
					case 'Past Day':   $html .= $this->getHTML_PurchasesDay();   break;
					case 'Past Week':  $html .= $this->getHTML_PurchasesWeek();  break;
					case 'Past Month': $html .= $this->getHTML_PurchasesMonth(); break;
					case 'Past Year':  $html .= $this->getHTML_PurchasesYear();  break;
				}
			break;
		}

		// close the db connection
		mysql_close($this->conn);

		return $html;
	}

	function _getCount($timestamp, $gap=0)
	{
		// account for timezone offsets
		$timestamp -= $this->Mint->cfg['offset'] * 3600;

		$timestamp2 = $timestamp + $gap;

		$query = mysql_query('SELECT COUNT(*) FROM '.$this->conf['db_prefix'].'_simple_commerce_purchases'
		                     . ' WHERE purchase_date >= '.$timestamp
		                     . ($gap ? ' AND purchase_date < '.$timestamp2 : '')
		                     . ($this->filter ? ' AND item_id = '.$this->filter : '')
		                     . ' AND item_cost != "0.00"',
		                    $this->conn);
		return mysql_result($query, 0);
	}

	/**
	 * Get Graph HTML for Past Day
	 */
	function getHTML_PurchasesDay() 
	{
		$graphData = array(
			'titles' => array(
				'background' => 'Purchases'
			),
			'key'    => array(
				'background' => 'Purchases'
			)
		);

		$high = 0;
		$hour = $this->Mint->getOffsetTime('hour');

		// Past 24 hours
		for ($i = 0; $i < 24; $i++) 
		{
			$timestamp = $hour - ($i * 3600);
			$count = $this->_getCount($timestamp, 3600); // 60 * 60

			if ($count > $high) $high = $count;
			$twelve = $this->Mint->offsetDate('G', $timestamp) == 12;
			$twentyFour = $this->Mint->offsetDate('G', $timestamp) == 0;
			$hourLabel = $this->Mint->offsetDate('g', $timestamp);

			$graphData['bars'][] = array
			(
				$count,
				0,
				($twelve) ? 'Noon' : (($twentyFour) ? 'Midnight' : (($hourLabel == 3 || $hourLabel == 6 || $hourLabel == 9) ? $hourLabel : '')),
				$this->Mint->formatDateRelative($timestamp, 'hour'),
				($twelve || $twentyFour) ? 1 : 0
			);
		}

		$graphData['bars'] = array_reverse($graphData['bars']);
		return $this->getHTML_Graph($high, $graphData);
	}

	/**
	 * Get Graph HTML for Past Week
	 */
	function getHTML_PurchasesWeek() 
	{
		$graphData = array
		(
			'titles' => array(
				'background' => 'Purchases'
			),
			'key'    => array(
				'background' => 'Purchases'
			)
		);

		$high = 0;
		$day = $this->Mint->getOffsetTime('today');

		// Past 7 days
		for ($i = 0; $i < 7; $i++) 
		{
			$timestamp = $day - ($i * 60 * 60 * 24);
			$count = $this->_getCount($timestamp, 86400); // 60 * 60 * 24

			if ($count > $high) $high = $count;
			$dayOfWeek = $this->Mint->offsetDate('w', $timestamp);
			$dayLabel = substr($this->Mint->offsetDate('D', $timestamp), 0, 2);

			$graphData['bars'][] = array
			(
				$count,
				0,
				($dayOfWeek == 0) ? '' : (($dayOfWeek == 6) ? 'Weekend' : $dayLabel),
				$this->Mint->formatDateRelative($timestamp, 'day'),
				($dayOfWeek == 0 || $dayOfWeek == 6) ? 1 : 0
			);
		}

		$graphData['bars'] = array_reverse($graphData['bars']);
		return $this->getHTML_Graph($high, $graphData);
	}

	/**
	 * Get Graph HTML for Past Month
	 */
	function getHTML_PurchasesMonth() 
	{
		$graphData = array
		(
			'titles' => array (
				'background' => 'Purchases'
			),
			'key' => array(
				'background' => 'Purchases'
			)
		);

		$high = 0;
		$week = $this->Mint->getOffsetTime('week');
		
		// Past 5 weeks
		for ($i = 0; $i < 5; $i++)
		{
			$timestamp = $week - ($i * 60 * 60 * 24 * 7);
			$count = $this->_getCount($timestamp, 604800); // 60 * 60 * 24 * 7

			if ($count > $high) $high = $count;

			$graphData['bars'][] = array
			(
				$count,
				0,
				$this->Mint->formatDateRelative($timestamp, "week", $i),
				$this->Mint->offsetDate('D, M j', $timestamp),
				($i == 0) ? 1 : 0
			);
		}

		$graphData['bars'] = array_reverse($graphData['bars']);
		return $this->getHTML_Graph($high, $graphData);
	}

	/**
	 * Get Graph HTML for Past Year
	 */
	function getHTML_PurchasesYear() 
	{
		$graphData = array
		(
			'titles' => array (
				'background' => 'Purchases'
			),
			'key' => array(
				'background' => 'Purchases'
			)
		);

		$high = 0;
		$month = $this->Mint->getOffsetTime('month');

		// Past 12 months
		for ($i = 0; $i < 12; $i++)
		{
			if ($i == 0)
			{
				$timestamp = $month;
				$days = 31;
			}
			else
			{
				$days = $this->Mint->offsetDate('t', $this->Mint->offsetMakeGMT(0, 0, 0, $this->Mint->offsetDate('n', $month)-1, 1, $this->Mint->offsetDate('Y', $month))); // days in the month
				$timestamp = $month - ($days * 24 * 3600);
			}
			$month = $timestamp;
			$count = $this->_getCount($timestamp, 86400 * $days);

			if ($count > $high) $high = $count;

			$graphData['bars'][] = array
			(
				$count,
				0,
				($i == 0) ? 'This Month' : $this->Mint->offsetDate(' M', $timestamp),
				$this->Mint->offsetDate('F', $timestamp),
				($i == 0) ? 1 : 0
			);
		}

		$graphData['bars'] = array_reverse($graphData['bars']);
		return $this->getHTML_Graph($high, $graphData);
	}

}

/* End of file class.php */
/* Location: ./mint/pepper/brandonkelly/scmpurchases/class.php */