<?php
namespace HighCharts;

abstract class AbstractChartOptions implements ChartOptionsInterface, \ArrayAccess{
  function getOptions() {
    $optArray = array();
    $props = get_object_vars($this);
    foreach($props as $name => $value) {
      if($name == '_container')
        continue;
      if ($value instanceof \HighCharts\ChartOptionsInterface) {
        $opts = $value->getOptions();
        if(count($opts) > 0)
          $optArray[$name] = $opts;
      } else if ($value !== null) {
        $optArray[$name] = $value;
      }
    }
    //_container contains all the vars set by array offset that had invalid names
    if(count($this->_container) > 0 )
      return array_merge($this->_container, $optArray);
      
    return $optArray;
  } 
  
  //Contains vars that were set via array offset that had invalid names for properties
  private $_container = array();
  
  function offsetExists($offset) {
    if($this->checkOffset($offset))
      return isset($this->$offset);
    if(array_key_exists($offset, $this->_container))
      return true;
  }
  
  function offsetSet($offset, $value) {
    if($this->checkOffset($offset))
      $this->$offset = $value;
    else
      $this->_container[$offset] = $value;
  }
  
  function offsetGet($offset) {
    if($this->offsetExists($offset)) {
      if($this->checkOffset($offset))
        return $this->$offset;
      return $this->_container[$offset];
    }
    return null;
  }
    
  function checkOffset($offset) {
    if ($offset !== '_container' && preg_match('%/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/%D', $offset)) {
      return true;
    } else {
      return false;
    }
  }
  
  function offsetUnset($offset) {
    if($this->offsetExists($offset)) {
      if($this->checkOffset($offset))
        unset($this->$offset);
      else
        unset($this->_container[$offset]);
    }
  }
  
  public function __get($name) {
    $this->$name = new ChartOptions;
    return $this->$name;
  }  
}