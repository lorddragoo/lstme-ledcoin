<?php if (!defined('BASEPATH')) {
	exit('No direct script access allowed');
}

	/**
	 * Tests if LEDCOIN quantity exceed daily limit.
	 *
	 * @param double      $quantity quantity to check.
	 * @param null|string $date     date to check, leave null for current day.
	 *
	 * @return bool
	 */
	function operations_ledcoin_limit_check($quantity, $date = NULL) {
		$daily_limit = operations_ledcoin_daily_limit($date);

		if ($daily_limit > 0) {
			$used_amount = operations_ledcoin_added_in_day($date);

			if ($quantity <= ($daily_limit - $used_amount)) {
				return TRUE;
			}
		}

		return FALSE;
	}

	/**
	 * Get limit from database for specified date.
	 *
	 * @param null|string $date date from which limit will be obtained, leave null for current day.
	 *
	 * @return double daily limit.
	 */
	function operations_ledcoin_daily_limit($date = NULL) {
		if ($date === NULL) {
			$date = date('Y-m-d');
		} else {
			if (!preg_match('/^[0-9]{4}-[0-9]{1,2}-[0-9]{1,2}$/', $date)) {
				return 0.0;
			}

			list($year, $month, $day) = explode('-', $date);

			if (!checkdate($month, $day, $year)) {
				return 0.0;
			}
		}

		$limit = new Limit();
		$limit->get_where(array('date' => $date));

		if ($limit->exists()) {
			return (double)$limit->daily_limit;
		}

		return 0.0;
	}

	/**
	 * Get amount of added LEDCOIN in specified date.
	 *
	 * @param null|string $date date from which amount of added LEDCOIN will be obtained, leave null for current day.
	 *
	 * @return double LEDCOIN added in day.
	 */
	function operations_ledcoin_added_in_day($date = NULL) {
		if ($date === NULL) {
			$date = date('Y-m-d');
		} else {
			if (!preg_match('/^[0-9]{4}-[0-9]{1,2}-[0-9]{1,2}$/', $date)) {
				return 0.0;
			}

			list($year, $month, $day) = explode('-', $date);

			if (!checkdate($month, $day, $year)) {
				return 0.0;
			}
		}

		$date_from = $date . ' 00:00:00';
		$date_to   = $date . ' 23:59:59';

		$operations_addition = new Operation();
		$operations_addition->where('type', Operation::TYPE_ADDITION);
		$operations_addition->select_sum('amount', 'amount_sum');
		$operations_addition->where('created >=', $date_from);
		$operations_addition->where('created <=', $date_to);
		$operations_addition->get();

		return (double)$operations_addition->amount_sum;
	}

	/**
	 * Get LEDCOIN multiplier for specified date.
	 * Will use constants ledcoin_multiplier_min and ledcoin_multiplier_max from application config file.
	 *
	 * @param null|string $date date from which multiplier will be obtained, leave null for current day.
	 *
	 * @return double value of multiplier.
	 */
	function operations_ledcoin_multiplier($date = NULL) {
		$limit = operations_ledcoin_daily_limit($date);
		$added = operations_ledcoin_added_in_day($date);
		if ($added == 0) {
			$multiplier = INF;
		} else {
			$multiplier = $limit / $added;
		}

		$CI =& get_instance();
		$CI->config->load('application');
		$min = (double)$CI->config->item('ledcoin_multiplier_min');
		$max = (double)$CI->config->item('ledcoin_multiplier_max');

		if ($multiplier < $min) {
			$multiplier = $min;
		} elseif ($multiplier > $max) {
			$multiplier = $max;
		}

		$multiplier *= 1000;
		$multiplier = (double)((double)round($multiplier) / 1000.0);

		return $multiplier;
	}