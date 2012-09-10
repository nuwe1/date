<?php

/**
 * @file
 * Definition of Drupal\date_api\DateGranularity.
 */
namespace Drupal\date_api;

/**
 * This class keeps track of the granularity of a date,
 * either by checking which values were provided in an array or which
 * date parts were identified in the input format. 
 *
 */
class DateGranularity {

  // Static values used in massaging this date.
  public static $granularity_parts = array('year', 'month', 'day', 'hour', 'minute', 'second');
  public static $date_parts = array('year', 'month', 'day');
  public static $time_parts = array('hour', 'minute', 'second');

  // An array to store the granularity of this date, as an array of date parts.
  public $granularity = array();

  // Flags to indicate whether the date has time and date parts.
  public $time_only = FALSE;
  public $date_only = FALSE;

  public function __construct($array = NULL) {
    if (!empty($array)) {
      $this->setGranularityFromArray($array);
    }
  }

  /**
   * Adds a granularity entry to the array.
   *
   * @param string $part
   *   A single date part.
   */
  public function addGranularity($part) {
    $this->granularity[] = $part;
    $this->granularity = array_unique($this->granularity);
  }

  /**
   * Removes a granularity entry from the array.
   *
   * @param string $part
   *   A single date part.
   */
  public function removeGranularity($part) {
    if ($key = array_search($part, $this->granularity)) {
      unset($this->granularity[$key]);
    }
  }

  /**
   * Checks granularity array for a given entry.
   *
   * @param array|null $parts
   *   An array of date parts. Defaults to NULL.
   *
   * @returns bool
   *   TRUE if the date part is present in the date's granularity.
   */
  public static function hasGranularity($parts) {
    if (is_array($parts)) {
      foreach ($parts as $part) {
        if (!in_array($part, self::$granularity)) {
          return FALSE;
        }
      }
      return TRUE;
    }
    return in_array($parts, self::$granularity);
  }

  /**
   * Removes unwanted date parts from a date.
   *
   * In common usage we should not unset timezone through this.
   *
   * @param array $array
   *   An array of date parts.
   */
  public function limitGranularity($array = NULL) {
    if (empty($array)) {
      $array = $this->granularity;
    }
    foreach (self::$granularity_parts as $key => $val) {
      if (!in_array($val, $array)) {
        unset($this->granularity[$key]);
      }
    }
  }

  /**
   * Determines the granularity of a date based on the constructor's arguments.
   *
   * @param string $time
   *   A date string.
   */
  public function setGranularityFromTime($time) {
    $this->granularity = array();

    $temp = date_parse($time);
    foreach ($this->granularity_parts as $part) {
      if ((isset($temp[$part]) && is_numeric($temp[$part]))) {
        $this->addGranularity($part);
      }
    }
  }

  /**
   * Determines the granularity of a date based on the constructor's arguments.
   *
   * @param array $array
   *   An array of date values, keyed by date part.
   */
  public function setGranularityFromArray($array) {
    $this->granularity = array();
    foreach ($array as $part => $value) {
      if (is_numeric($value)) {
        $this->addGranularity($part);
      }
    }
  }

  /**
   * Sorts a granularity array.
   *
   * @param array $array
   *   An array of date parts.
   */
  public function sorted($array = NULL) {
    if (empty($array)) {
      $array = $this->granularity;
    }
    return array_intersect($this->granularity_parts, $array);
  }
  
  /**
   * Constructs an array of granularity based on a given precision.
   *
   * @param string $precision
   *   A granularity item.
   *
   * @return array
   *   A granularity array containing the given precision and all those above it.
   *   For example, passing in 'month' will return array('year', 'month').
   */
  public function arrayFromPrecision($precision) {
    $granularity_array = $this->granularity_parts;
    switch ($precision) {
      case 'year':
        return array_slice($granularity_array, -6, 1);
      case 'month':
        return array_slice($granularity_array, -6, 2);
      case 'day':
        return array_slice($granularity_array, -6, 3);
      case 'hour':
        return array_slice($granularity_array, -6, 4);
      case 'minute':
        return array_slice($granularity_array, -6, 5);
      default:
        return $granularity_array;
    }
  }
  
  /**
   * Give a granularity array, return the highest precision.
   *
   * @param array $array
   *   An array of date parts.
   *
   * @return string
   *   The most precise element in a granularity array.
   */
  public function precision($array = NULL) {
    if (empty($array)) {
      $array = $this->granularity;
    }
    $input = $this->sorted($array);
    return array_pop($input);
  }
  
  /**
   * Constructs a valid DATETIME format string, limited to a certain granularity.
   */
  public function format($granularity) {
    if (is_array($granularity)) {
      $granularity = $this->precision($granularity);
    }
    $format = 'Y-m-d H:i:s';
    switch ($granularity) {
      case 'year':
        return substr($format, 0, 1);
      case 'month':
        return substr($format, 0, 3);
      case 'day':
        return substr($format, 0, 5);
      case 'hour';
        return substr($format, 0, 7);
      case 'minute':
        return substr($format, 0, 9);
      default:
        return $format;
    }
  }

  /**
   * Limits a date format to include only elements from a given granularity array.
   *
   * Example:
   *   $this->limitFormat('F j, Y - H:i', array('year', 'month', 'day'));
   *   returns 'F j, Y'
   *
   * @param string $format
   *   A date format string.
   * @param array $array
   *   An array of allowed date parts, all others will be removed.
   *
   * @return string
   *   The format string with all other elements removed.
   */
  public function limitFormat($format, $array = NULL) {
    if (empty($array)) {
      $array = $this->granularity;
    }

    // If punctuation has been escaped, remove the escaping. Done using strtr()
    // because it is easier than getting the escape character extracted using
    // preg_replace().
    $replace = array(
      '\-' => '-',
      '\:' => ':',
      "\'" => "'",
      '\. ' => ' . ',
      '\,' => ',',
    );
    $format = strtr($format, $replace);
  
    // Get the 'T' out of ISO date formats that don't have both date and time.
    if (!$this->hasTime($array) || !$this->hasDate($array)) {
      $format = str_replace('\T', ' ', $format);
      $format = str_replace('T', ' ', $format);
    }
  
    $regex = array();
    if (!$this->hasTime($array)) {
      $regex[] = '((?<!\\\\)[a|A])';
    }
    // Create regular expressions to remove selected values from string.
    // Use (?<!\\\\) to keep escaped letters from being removed.
    foreach (self::nongranularity($array) as $element) {
      switch ($element) {
        case 'year':
          $regex[] = '([\-/\.,:]?\s?(?<!\\\\)[Yy])';
          break;
        case 'day':
          $regex[] = '([\-/\.,:]?\s?(?<!\\\\)[l|D|d|dS|j|jS|N|w|W|z]{1,2})';
          break;
        case 'month':
          $regex[] = '([\-/\.,:]?\s?(?<!\\\\)[FMmn])';
          break;
        case 'hour':
          $regex[] = '([\-/\.,:]?\s?(?<!\\\\)[HhGg])';
          break;
        case 'minute':
          $regex[] = '([\-/\.,:]?\s?(?<!\\\\)[i])';
          break;
        case 'second':
          $regex[] = '([\-/\.,:]?\s?(?<!\\\\)[s])';
          break;
        case 'timezone':
          $regex[] = '([\-/\.,:]?\s?(?<!\\\\)[TOZPe])';
          break;
  
      }
    }
    // Remove empty parentheses, brackets, pipes.
    $regex[] = '(\(\))';
    $regex[] = '(\[\])';
    $regex[] = '(\|\|)';
  
    // Remove selected values from string.
    $format = trim(preg_replace($regex, array(), $format));
    // Remove orphaned punctuation at the beginning of the string.
    $format = preg_replace('`^([\-/\.,:\'])`', '', $format);
    // Remove orphaned punctuation at the end of the string.
    $format = preg_replace('([\-/,:\']$)', '', $format);
    $format = preg_replace('(\\$)', '', $format);
  
    // Trim any whitespace from the result.
    $format = trim($format);
  
    // After removing the non-desired parts of the format, test if the only things
    // left are escaped, non-date, characters. If so, return nothing.
    // Using S instead of w to pick up non-ASCII characters.
    $test = trim(preg_replace('(\\\\\S{1,3})', '', $format));
    if (empty($test)) {
      $format = '';
    }
  
    return $format;
  }
  
  /**
   * Converts a format to an ordered array of granularity parts.
   *
   * Example:
   *   DateGranularity::formatOrder('m/d/Y H:i')
   *   returns
   *     array(
   *       0 => 'month',
   *       1 => 'day',
   *       2 => 'year',
   *       3 => 'hour',
   *       4 => 'minute',
   *     );
   *
   * @param string $format
   *   A date format string.
   *
   * @return array
   *   An array of ordered granularity elements from the given format string.
   */
  public static function formatOrder($format) {
    $order = array();
    if (empty($format)) {
      return $order;
    }
  
    $max = strlen($format);
    for ($i = 0; $i <= $max; $i++) {
      if (!isset($format[$i])) {
        break;
      }
      switch ($format[$i]) {
        case 'd':
        case 'j':
          $order[] = 'day';
          break;
        case 'F':
        case 'M':
        case 'm':
        case 'n':
          $order[] = 'month';
          break;
        case 'Y':
        case 'y':
          $order[] = 'year';
          break;
        case 'g':
        case 'G':
        case 'h':
        case 'H':
          $order[] = 'hour';
          break;
        case 'i':
          $order[] = 'minute';
          break;
        case 's':
          $order[] = 'second';
          break;
      }
    }
    return $order;
  }
  
  /**
   * Strips out unwanted granularity elements.
   *
   * @param array $array
   *   An array like ('year', 'month', 'day', 'hour', 'minute', 'second');
   *
   * @return array
   *   A reduced set of granularitiy elements.
   */
  public function nongranularity($array = NULL) {
    if (empty($array)) {
      $array = $this->granularity;
    }
    return array_diff($this->granularity_parts, (array) $array);
  }

  /**
   * Determines if the granularity contains a time portion.
   *
   * @param array $array
   *   An array of allowed date parts, all others will be removed.
   *
   * @return bool
   *   TRUE if the granularity contains a time portion, FALSE otherwise.
   */
  public function hasTime($array = NULL) {
    if (empty($array)) {
      $array = $this->$granularity;
    }
    return (bool) count(array_intersect($array, $this->time_parts));
  }
  
  /**
   * Determines if the granularity contains a date portion.
   *
   * @param array $array
   *   An array of allowed date parts, all others will be removed.
   *
   * @return bool
   *   TRUE if the granularity contains a date portion, FALSE otherwise.
   */
  public function hasDate($array = NULL) {
    if (empty($array)) {
      $array = $this->$granularity;
    }
    return (bool) count(array_intersect($array, $this->date_parts));
  }

  /**
   * Helper function to get a format for a specific part of a date field.
   *
   * @param string $part
   *   The date field part, either 'time' or 'date'.
   * @param string $format
   *   A date format string.
   *
   * @return string
   *   The date format for the given part.
   */
  public function partFormat($part, $format) {
    switch ($part) {
      case 'date':
        return $this->limitFormat($format, $this->$date_parts);
      case 'time':
        return $this->limitFormat($format, $this->$time_parts);
      default:
        return $this->limitFormat($format, array($part));
    }
  }

  /**
   * Determines if a date is valid for a given granularity.
   *
   * @param array|null $granularity
   *   An array of date parts. Defaults to NULL.
   * @param bool $flexible
   *   TRUE if the granuliarty is flexible, FALSE otherwise. Defaults to FALSE.
   *
   * @return bool
   *   Whether a date is valid for a given granularity.
   */
  public function validGranularity($granularity = NULL, $flexible = FALSE) {
    $true = $this->hasGranularity() && (!$granularity || $flexible || $this->hasGranularity($granularity));
    if (!$true && $granularity) {
      foreach ((array) $granularity as $part) {
        if (!$this->hasGranularity($part)) {
          $this->errors[$part] = t("The @part is missing.", array('@part' => $part));
        }
      }
    }
    return $true;
  }


}