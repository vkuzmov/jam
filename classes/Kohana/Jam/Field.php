<?php defined('SYSPATH') OR die('No direct script access.');
/**
 * Core class that all fields must extend
 *
 * @package    Jam
 * @category   Fields
 * @author     Jonathan Geiger
 * @copyright  (c) 2010-2011 Jonathan Geiger
 * @license    http://www.opensource.org/licenses/isc-license.txt
 */
abstract class Kohana_Jam_Field extends Jam_Attribute {

	/**
	 * @var  string  the model's name
	 */
	public $model;

	/**
	 * @var  string  the column's name in the database
	 */
	public $column;

	/**
	 * @var  string  a pretty name for the field
	 */
	public $label;

	/**
	 * @var  string  the field's name in the form
	 */
	public $name;

	/**
	* @var  boolean  a primary key field.
	*/
	public $primary = FALSE;

	/**
	* @var  boolean  the column is present in the database table. Default: TRUE
	*/
	public $in_db = TRUE;

	/**
	* @var  mixed  default value
	*/
	public $default = NULL;

	/**
	 * @var  boolean  whether or not empty() values should be converted to NULL
	 */
	public $convert_empty = FALSE;

	/**
	 * @var  mixed  the value to convert empty values to. This is only used if convert_empty is TRUE
	 */
	public $empty_value = NULL;

	/**
	 * @var  boolean  whether or not NULL values are allowed
	 */
	public $allow_null = TRUE;

	/**
	* @var  array  filters are called whenever data is set on the field
	*/
	public $filters = array();

	/**
	 * Sets all options.
	 *
	 * @param  array  $options
	 */
	public function __construct($options = array())
	{
		// Assume it's the column name
		if (is_string($options))
		{
			$this->column = $options;
		}
		elseif (is_array($options))
		{
			// Just throw them into the class as public variables
			foreach ($options as $name => $value)
			{
				$this->$name = $value;
			}
		}
		elseif ($options !== NULL)
		{
			throw new Kohana_Exception("Jam_Field options must be either string or an array of options");
		}

		// See if we need to allow_null values because of convert_empty
		if ($this->convert_empty AND $this->empty_value === NULL)
		{
			$this->allow_null = TRUE;
		}

		// Default value is going to be NULL if null is true
		// to mimic the SQL defaults.
		if ( ! array_key_exists('default', (array) $options) AND $this->allow_null)
		{
			$this->default = NULL;
		}

		// Default the empty value to NULL when allow_null is TRUE, but be careful not
		// to override a programmer-configured empty_value.
		if ( ! empty($options['allow_null']) AND ! array_key_exists('empty_value', (array) $options))
		{
			$this->empty_value = NULL;
		}

		if ( ! empty($options['filters']))
		{
			$this->filters = $options['filters'];
		}
	}

	/**
	 * This is called after construction so that fields can finish
	 * constructing themselves with a copy of the column it represents.
	 *
	 * @param   string  $model
	 * @param   string  $column
	 * @return  void
	 **/
	public function initialize(Jam_Meta $meta, $name)
	{
		parent::initialize($meta, $name);

		if ( ! $this->column)
		{
			$this->column = $name;
		}
	}

	/**
	 * Sets a particular value processed according
	 * to the class's standards.
	 *
	 * @param   mixed  $value
	 * @return  mixed
	 **/
	public function set(Jam_Validated $model, $value, $is_changed)
	{
		list($value, $return) = $this->_default($model, $value);

		return $value;
	}

	/**
	 * Returns a particular value processed according
	 * to the class's standards.
	 *
	 * @param   Jam_Model  $model
	 * @param   mixed        $value
	 * @return  mixed
	 **/
	public function get(Jam_Validated $model, $value, $is_changed)
	{
		return $value;
	}

	/**
	 * Called just before saving.
	 *
	 * If $in_db, it is expected to return a value suitable for insertion
	 * into the database.
	 *
	 * @param   Jam_Model  $model
	 * @param   mixed        $value
	 * @param   bool         $loaded
	 * @return  mixed
	 */
	public function convert(Jam_Validated $model, $value, $is_loaded)
	{
		return $value;
	}

	/**
	 * Shortcut for setting a filter
	 * @param  string $filter_name
	 * @param  array $values    values, defaults to array(':value')
	 * @return Jam_Field            $this
	 */
	public function filter($filter_name, $values = NULL)
	{
		$this->filters[] = array($filter_name, $values);
		return $this;
	}

	/**
	 * Potentially converts the value to NULL or default depending on
	 * the fields configuration. An array is returned with the first
	 * element being the new value and the second being a boolean
	 * as to whether the field should return the value provided or
	 * continue processing it.
	 *
	 * @param   mixed  $value
	 * @return  array
	 */
	protected function _default(Jam_Validated $model, $value)
	{
		$return = FALSE;

		$value = $this->run_filters($model, $value);

		// Convert empty values to NULL, if needed
		if ($this->convert_empty AND empty($value))
		{
			$value  = $this->empty_value;
			$return = TRUE;
		}

		// Allow NULL values to pass through untouched by the field
		if ($this->allow_null AND $value === NULL)
		{
			$value  = NULL;
			$return = TRUE;
		}


		return array($value, $return);
	}

	public function is_empty($value)
	{
		if ($this->convert_empty AND $this->empty_value !== NULL)
			return $value === $this->empty_value;

		return ($value === NULL OR $value === $this->default);
	}

	public function run_filters(Jam_Validated $model, $value)
	{
		if ( ! empty($this->filters))
		{
			foreach ($this->filters as $filter => $arguments)
			{
				if (is_numeric($filter))
				{
					$filter = $arguments;
					$arguments = array();
				}

				$value = $this->run_filter($model, $value, $filter, $arguments);
			}
		}
		return $value;
	}

	public function run_filter(Jam_Validated $model, $value, $filter, array $arguments = array())
	{
		$bound = array(
			':model' => $model,
			':field' => $this->name,
			':value' => $value,
		);

		$arguments = $arguments ? $arguments : array(':value');

		foreach ($arguments as & $argument)
		{
			if (is_string($argument) AND array_key_exists($argument, $bound))
			{
				// Replace with bound value
				$argument = $bound[$argument];
			}
		}

		$value = call_user_func_array($filter, $arguments);


		return $value;
	}
} // End Kohana_Jam_Field
