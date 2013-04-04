<?php

namespace HighCharts;
use HighCharts\ChartOptions\HighChartsOptions;
use \Zend\Json\Json;

class HighChartsChart {
  /**
   *
   * @var HighChartsOptions $options
   */
  public $options;
  
  private $_style = array();
  
  private $_class = array();

  private $_containerId;
  
  public function __construct() {
    $this->options = new HighChartsOptions();
    $this->setContainerId('highchart_' . uniqid());
  }
  
  public function renderContainer() {
    $renderTo = $this->_containerId;

    //Mux down a style for the container
    $compiledStyle = array();
    foreach($this->_style as $option => $value)
      $compiledStyle[] = $option . ':' . $value;
    $styleAttribute = count($compiledStyle) > 0?' style="' . implode(';', $compiledStyle) . '"':'';

    //Get classes
    if(count($this->_class) > 0)
      $class = ' class="' . implode(' ', $this->_class) . '"';
    else
      $class = '';
    return '<div'.$styleAttribute.$class.' id="'.$renderTo.'"></div>';
  }
  
  public function renderScript() {
    $jsVar = $this->_containerId;

    return "$(function() {var ".$jsVar."; ".$jsVar." = new Highcharts.Chart(".Json::encode($this->options->getOptions(), false, array('enableJsonExprFinder' => true)).");});";
  }
  
  public function render() {
    return $this->renderContainer() . "\n" . "<script type=\"text/javascript\">\n" . $this->renderScript() . "\n</script>";
  }
  
  public function setContainerId($id) {
      if(!empty($id)) {
          $this->_containerId = $id;
      }

      $this->options->chart->renderTo = $this->_containerId;
  }
  
  public function getContainerId($id) {
    return $this->_containerId;
  }
  
  public function setCSS($option, $value) {
    $this->_style[$option] = $value;
  }

  public function addClass($class) {
    if(array_search($class, $this->_class) === false)
      $this->_class[] = $class;
  }

  public function removeClass($class) {
    if(array_search($class, $this->_class) !== false)
      unset($this->_class[array_search($class, $this->_class)]);
  }
  
}
