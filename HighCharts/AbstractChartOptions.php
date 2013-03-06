<?php
namespace HighCharts;

abstract class AbstractChartOptions implements ChartOptionsInterface, \ArrayAccess{
  public function getOptions() {
    $optArray = array();
    $props = get_object_vars($this);
    foreach($props as $name => $value) {
      if($name === '___container')
        continue;
      if ($value instanceof \HighCharts\ChartOptionsInterface) {
        $opts = $value->getOptions();
        if(count($opts) > 0)
          $optArray[$name] = $opts;
      } else if ($value !== null) {
        $optArray[$name] = $value;
      }
    }
    //___container contains all the vars set by array offset that had invalid names
    if(count($this->___container) > 0 )
      return array_merge($this->___container, $optArray);
      
    return $optArray;
  } 
  
  //Contains vars that were set via array offset that had invalid names for properties
  private $___container = array();
  
  public function offsetExists($offset) {
    if(is_null($offset))
      return false;
    if($this->checkOffset($offset))
      return isset($this->$offset);
    if(array_key_exists($offset, $this->___container))
      return true;
  }
  
  public function offsetSet($offset, $value) {
    if(is_null($offset)) {
      $this->___container[] = $value;
    } else if($this->checkOffset($offset))
      $this->$offset = $value;
    else
      $this->___container[$offset] = $value;
  }
  
  public function &offsetGet($offset) {
    if(is_null($offset)) {
      $opts = new ChartOptions;
      $this->___container[] = $opts;
      end($this->___container);
      $key = key($this->___container);
      reset($this->___container);
      return $this->___container[$key];
    }
    
    if($this->offsetExists($offset)) {
      if($this->checkOffset($offset))
        return $this->$offset;
      return $this->___container[$offset];
    } else {
      $opts = new ChartOptions;
      if($this->checkOffset($offset)) {
        $this->$offset = $opts;
        return $this->$offset;
      }
      else {
        $this->___container[$offset] = $opts;
        return $this->___container[$offset];
      }
    }
  }
    
  public function checkOffset($offset) {
    if ($offset !== '___container' && preg_match('%/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/%D', $offset)) {
      return true;
    } else {
      return false;
    }
  }
  
  public function offsetUnset($offset) {
    if($this->offsetExists($offset)) {
      if($this->checkOffset($offset))
        unset($this->$offset);
      else
        unset($this->___container[$offset]);
    }
  }
  
  public function __get($name) {
    $this->$name = new ChartOptions;
    return $this->$name;
  }  
}