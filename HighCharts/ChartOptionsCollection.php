<?php
namespace HighCharts;
use Doctrine\Common\Collections\ArrayCollection;

class ChartOptionsCollection extends ArrayCollection implements ChartOptionsInterface{
  public function getOptions() {
    $options = array();
    foreach($this->toArray() as $option) {
      if($option instanceof AbstractChartOptions)
        $options[] = $option->getOptions();
      else 
        $options[] = $option;
    }
    return $options;      
  }
}