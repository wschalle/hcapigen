<?php

if ($this->__name__ !== null) {
    if ($this->__name__ instanceof \HighCharts\ChartOptionsInterface) {
      $opts = $this->__name__->getOptions();
      if(count($opts) > 0)
        $optArray['__name__'] = $opts;
    } else {
        $optArray['__name__'] = $this->__name__;
    }
}