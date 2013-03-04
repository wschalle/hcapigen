<?php

namespace HighCharts;
use HighCharts\ChartOptions\HighChartsGlobalOptions;
class HighCharts {
  /**
   *
   * @var HighChartsGlobalOptions $options 
   */
  private static $options;
  
  private static $_initialized = false;
  
  private static $scriptPath = 'scripts/';
  
  private static $_more = true;
  
  private static $_theme = 'default';
  
  public static function setOptions($options) {
    self::$options = $options;
  }
  
  public static function getOptions() {
    if(!(self::$options instanceof HighChartsGlobalOptions))
      self::$options = new HighChartsGlobalOptions;
    return self::$options;
  }
  
  public static function setTheme($theme) {
    self::$_theme = $theme;
  }
  
  public static function getTheme() {
    return self::$_theme;
  }
  
  /**
   * Controls whether or not Highcharts.more.js is included.
   * 
   * @param bool $bool
   */
  public static function more($bool) {
    self::$_more = $bool;
  }
  
  public static function setScriptPath($path) {
    self::$scriptPath = $path;
  }
    
  public static function init() {
    if(self::$_initialized)
      throw new Exception('HighCharts is already initialized. Cannot initialize twice.');
    if(!(self::$options instanceof HighChartsGlobalOptions))
      self::$options = new HighChartsGlobalOptions();
    $path = self::$scriptPath;
    if($path[strlen($path) - 1] != '/')
      $path .= "/";
      
    $rendered = self::scriptInclude($path . 'highcharts.js');
    if(self::$_more)
      $rendered .= self::scriptInclude($path . 'highcharts-more.js');
    $rendered .= self::scriptInclude($path . 'themes/' . self::$_theme . '.js');
    $rendered .= '<script type="text/javascript">Highcharts.setOptions('. json_encode(self::$options->getOptions()) . ');</script>' . "\n";
    self::$_initialized = true;
    return $rendered;
  }
  
  private static function scriptInclude($path) {
    return '<script type="text/javascript" src="' . $path . '"></script>' . "\n";
  }
  
}