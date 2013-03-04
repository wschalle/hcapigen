<?php
namespace HighCharts;

class Color extends AbstractChartOptions {
  public $type = self::STRING;
  public $value = "#FFFFFF";
  public $stops = array();
  
  public function getOptions() {
    if($this->type == self::STRING)
      return $this->value;
    else if ($this->type == self::LINEARGRADIENT)
      return array('linearGradient' => $this->value, 'stops' => $this->stops);
    else if ($this->type == self::RADIALGRADIENT)
      return array('radialGradient' => $this->value, 'stops' => $this->stops);
  }
  
  public function __construct() {
    $args = func_get_args();
    if(func_num_args() == 1 && is_string($args[0])) {
      $this->value = $args[0];      
    } else if (func_num_args() == 2) {
      $this->type = $args[0];
      $this->value = $args[1];
    } else if (func_num_args() > 2) {
      $this->type = $args[0];
      $this->value = $args[1];
      $this->stops = $args[2];
    } 
  }
  
  const STRING = 0;
  const LINEARGRADIENT = 1;
  const RADIALGRADIENT = 2;  
}