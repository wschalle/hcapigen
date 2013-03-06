<?php

class HCApiGenerator {

  public $loader;
  public $prettyprinter;
  public $lexer;
  public $factory;
  public $parser;
  public $basedir;
  public $outputdir = 'HighCharts/ChartOptions/';
  public $namespace = 'HighCharts\ChartOptions';

  public function __construct() {
    $this->prettyprinter = new PHPParser_PrettyPrinter_Default;
    $this->factory = new PHPParser_BuilderFactory;
    $this->lexer = new PHPParser_Lexer;
    $this->parser = new PHPParser_Parser($this->lexer);
    $this->loader = new PHPParser_TemplateLoader($this->parser, __DIR__ . '/templates', '.php');
  }
  
  public function createOutputDir() {
    if(!is_dir($this->outputdir))
      mkdir($this->outputdir);
  }

  public function generate($namespace) {
    $this->createOutputDir();
    $this->namespace = $namespace;
    $classes = $this->getClasses();
    $globalClasses = $classes['globals'];
    $classes = $classes['classes'];
    $this->processClasses($classes);
    $this->generateHCGlobalOptions($globalClasses);
  }

  public function getClasses() {
    //Grab the main chart api data
    $chartData = $this->curlGetJSON("http://api.highcharts.com/option/highcharts/main");

    //Items to exclude when generating the chart API wrapper -- these are globally configured options and can't be set per-graph.
    $exclude = array('global', 'lang');
    $classes = array();
    $globalClasses = array();
    foreach ($chartData as $key => $value) {
      //Populate class list based on children of the main object
      if (array_search($value['name'], $exclude) !== false)
        $ex = true;
      else
        $ex = false;
        
      $name = $value['name'];
      $className = ucfirst($value['name']);
      if($className == 'Global')
        $className = 'HCGlobal';
      $description = $value['description'];
      
      if($value['returnType'] == 'Array<Object>')
        $returnType = $className . '[]';
      else
        $returnType = $className;
      //We just need the name, classname(generated), and description.
      if($ex)
        $globalClasses[] = array('name' => $name, 'className' => $className, 'description' => $description, 'returnType' => $returnType, 'originalName' => $name);
      else
        $classes[] = array('name' => $name, 'className' => $className, 'description' => $description, 'returnType' => $returnType, 'originalName' => $name);
    }
    return array('classes' => $classes, 'globals' => $globalClasses);
  }

  /**
   * Generates an options class. 
   * 
   * @return array array('property', 
   * @param type $class
   */
  public function generateClass($class) {
    $gsTemplate = $this->loader->load('propertyTemplate');
    $namespaceTemplate = $this->loader->load('namespaceTemplate');
    
    $fp = fopen($this->outputdir . $class['className'] . '.php', 'w');
    $newClass = $this->factory->class($class['className'])->extend('\HighCharts\AbstractChartOptions');
    $newClassConstructor = $this->factory->method('__construct');
    $namespaceStmt = $namespaceTemplate->getStmts(array('name' => $this->namespace));
    $nsNode = $namespaceStmt[0];
    $properties = $this->getProperties($class['originalName']);
    $propnames = array();
    
    foreach ($properties as $property) {
    //Generate getters/setters for properties      
      $placeHolders = array('name' => $property['name'], 'type' => $property['returnType'], 'description' => $this->processDescription($property['description']));
      $propertyStmts = $gsTemplate->getStmts($placeHolders);
      $thisReference = new PHPParser_Node_Expr_PropertyFetch(new PHPParser_Node_Expr_Variable('this'), $property['name']);
      if($property['isParent']) { //Recursively generate classes
        $this->generateClass($property);      
        
        if(strpos($property['returnType'], '[]') !== false) { // Create an empty collection for array type parameters that have child classes
          $collectionstmt = new PHPParser_Node_Expr_Assign($thisReference, new PHPParser_Node_Expr_New(new PHPParser_Node_Name('\HighCharts\ChartOptionsCollection')));
          $newClassConstructor->addStmt($collectionstmt);
        } else {        
          $newClassConstructor->addStmt(new PHPParser_Node_Expr_Assign($thisReference, new PHPParser_Node_Expr_New(new PHPParser_Node_Name($property['className']))));
        }
      } else {
        if(strpos($property['returnType'], '[]') !== false) { // Create an empty collection for unspecified array types
          $collectionstmt = new PHPParser_Node_Expr_Assign($thisReference, new PHPParser_Node_Expr_New(new PHPParser_Node_Name('\HighCharts\ChartOptions')));
          $newClassConstructor->addStmt($collectionstmt);
        }
      }

      $newClass->addStmts($propertyStmts[0]->stmts);
      $propnames[] = $property['name'];        
    }
    //$getOptionsMethod = $this->generateGetOptionsMethodStatement($propnames);
    //$newClass->addStmt($getOptionsMethod);
    $newClass->addStmt($newClassConstructor);
    $node = $newClass->getNode();
    $node->setAttribute('comments', array(new PHPParser_Comment_Doc("/**\n * " . $class['className'] . "\n *\n * " . $this->processDescription($class['description']) . "\n */")));
    
    $classDefinition = $this->prettyprinter->prettyPrint(array($nsNode, $node));
    fwrite($fp, "<?php\n$classDefinition");
    fclose($fp);

  }
  
  /**
   * Main chart options processor
   * 
   * Processes the option classes for the main chart call
   *   
   * @param array<array> $classes An array of arrays describing each class to generate
   */
  public function processClasses($classes) {
    $publicPropTemplate = $this->loader->load('publicPropTemplate');
    $gsTemplate = $this->loader->load('propertyTemplate');
    $namespaceTemplate = $this->loader->load('namespaceTemplate');
    //Instantiate the main options class
    $HighChartsOptionsClass = $this->factory->class('HighChartsOptions')
      ->extend('\HighCharts\AbstractChartOptions');
    //Start the constructor
    $HighChartsOptionsConstructor = $this->factory->method('__construct');

    //Generate the namespace Statement
    $namespaceStmt = $namespaceTemplate->getStmts(array('name' => $this->namespace));
    $nsnode = $namespaceStmt[0];
    //Store the names of the generated classes
    $classnames = array();
    //Loop each class and create it.    
    foreach ($classes as $class) {
      $hcPropPlaceHolders = array('name' => $class['name'], 'description' => $this->processDescription($class['description']), 'type' => $class['returnType']);
      $hcProp = $publicPropTemplate->getStmts($hcPropPlaceHolders);
      $HighChartsOptionsClass->addStmt($hcProp[0]->stmts[0]);
      $classnames[] = $class['name'];
      $this->generateClass($class);
      /*
      $fp = fopen($this->outputdir . $class['className'] . '.php', 'w');
      $newClass = $this->factory->class($class['className'])
        ->extend('\HighCharts\AbstractChartOptions');

      $classnames[] = $class['name'];
      $properties = $this->getProperties($class['originalName']);
      var_dump($properties);
      $propnames = array();
      //Generate the properties for the class
      foreach ($properties as $property) {
        $placeHolders = array('name' => $property['name'], 'type' => $property['returnType'], 'description' => $this->processDescription($property['description']));
        $propertyStmts = $gsTemplate->getStmts($placeHolders);

        $newClass->addStmts($propertyStmts[0]->stmts);
        $propnames[] = $property['name'];
      }
      $getOptionsMethod = $this->generateGetOptionsMethodStatement($propnames);
      $newClass->addStmt($getOptionsMethod);
      $node = $newClass->getNode();
      $node->setAttribute('comments', array(new PHPParser_Comment_Doc("/**\n * " . $class['className'] . "\n *\n * " . $this->processDescription($class['description']) . "\n *")));
      //$stmts[] = $node;
      $classDefinition = $this->prettyprinter->prettyPrint(array($nsnode, $node));
      fwrite($fp, "<?php\n$classDefinition");
      fclose($fp);*/
      $thisReference = new PHPParser_Node_Expr_PropertyFetch(new PHPParser_Node_Expr_Variable('this'), $class['name']);
      
      //Generate the 'new ArrayCollection' statement
      if(strpos($class['returnType'], '[]') !== false) {
        $collectionstmt = new PHPParser_Node_Expr_Assign($thisReference, new PHPParser_Node_Expr_New(new PHPParser_Node_Name('\HighCharts\ChartOptionsCollection')));
        $HighChartsOptionsConstructor->addStmt($collectionstmt);
      }
      else {        
        $HighChartsOptionsConstructor->addStmt(new PHPParser_Node_Expr_Assign($thisReference, new PHPParser_Node_Expr_New(new PHPParser_Node_Name($class['className']))));
      }
    }
    $HighChartsOptionsClass->addStmt($HighChartsOptionsConstructor);
    //$HighChartsOptionsClass->addStmt($this->generateGetOptionsMethodStatement($classnames));
    $fp = fopen($this->outputdir . 'HighChartsOptions.php', 'w');
    fwrite($fp, "<?php\n" . $this->prettyprinter->prettyPrint(array($nsnode, $HighChartsOptionsClass->getNode())));
    fclose($fp);
  }
  
  private function generateHCGlobalOptions($classes) {
    $publicPropTemplate = $this->loader->load('publicPropTemplate');
    $gsTemplate = $this->loader->load('propertyTemplate');
    $namespaceTemplate = $this->loader->load('namespaceTemplate');
    
    $hcGlobalOptions = $this->factory->class('HighChartsGlobalOptions')
      ->extend('\HighCharts\AbstractChartOptions');
    $hcGlobalOptionsConstructor = $this->factory->method('__construct');
    
    //Generate the namespace Statement
    $namespaceStmt = $namespaceTemplate->getStmts(array('name' => $this->namespace));
    $nsnode = $namespaceStmt[0];
    $classnames = array();
    
    foreach ($classes as $class) {
      $hcPropPlaceHolders = array('name' => $class['name'], 'description' => $this->processDescription($class['description']));
      $hcProp = $publicPropTemplate->getStmts($hcPropPlaceHolders);

      $hcGlobalOptions->addStmt($hcProp[0]->stmts[0]);
      $fp = fopen($this->outputdir . $class['className'] . '.php', 'w');
      $stmt = $this->factory->class($class['className'])
        ->extend('\HighCharts\AbstractChartOptions');

      $classnames[] = $class['name'];
      $properties = $this->getProperties($class['name']);
      $propnames = array();
      foreach ($properties as $property) {
        $placeHolders = array('name' => $property['name'], 'type' => $property['returnType'], 'description' => $this->processDescription($property['description']));
        $propertyStmts = $gsTemplate->getStmts($placeHolders);

        $stmt->addStmts($propertyStmts[0]->stmts);
        $propnames[] = $property['name'];
      }
      //$getOptionsMethod = $this->generateGetOptionsMethodStatement($propnames);
      //$stmt->addStmt($getOptionsMethod);
      $node = $stmt->getNode();
      $node->setAttribute('comments', array(new PHPParser_Comment_Doc("/**\n * " . $class['className'] . "\n *\n * " . $this->processDescription($class['description']) . "\n */")));
      //$stmts[] = $node;
      $classDefinition = $this->prettyprinter->prettyPrint(array($nsnode, $node));
      fwrite($fp, "<?php\n$classDefinition");
      fclose($fp);
      $thisReference = new PHPParser_Node_Expr_PropertyFetch(new PHPParser_Node_Expr_Variable('this'), $class['name']);
      $hcGlobalOptionsConstructor->addStmt(new PHPParser_Node_Expr_Assign($thisReference, new PHPParser_Node_Expr_New(new PHPParser_Node_Name($class['className']))));
    }
    $hcGlobalOptions->addStmt($hcGlobalOptionsConstructor);
    //$hcGlobalOptions->addStmt($this->generateGetOptionsMethodStatement($classnames));
    $fp = fopen($this->outputdir . 'HighChartsGlobalOptions.php', 'w');
    fwrite($fp, "<?php\n" . $this->prettyprinter->prettyPrint(array($nsnode, $hcGlobalOptions->getNode())));
    fclose($fp);
  }
  
  private function generateGetOptionsMethodStatement($options) {
    $method = $this->factory->method('getOptions')
      ->makePublic();
    // array()
    $arrayDeclaration = new PHPParser_Node_Expr_Array();
    // $optArray = array();
    $arrayVar = new PHPParser_Node_Expr_Variable('optArray');
    $arrayAssignment = new PHPParser_Node_Expr_Assign($arrayVar, $arrayDeclaration);
    $method->addStmt($arrayAssignment);
    $tpl = $this->loader->load('ifNullOptionTemplate');
    foreach($options as $option) {
      
      $opts = array('name' => $option);
      $method->addStmts($tpl->getStmts($opts));
    }
    $method->addStmt(new PHPParser_Node_Stmt_Return($arrayVar));
    
    return $method;
  }
  
  public function getProperties($name) {
    $data = $this->curlGetJSON("http://api.highcharts.com/option/highcharts/child/$name");
    $properties = array();
    foreach ($data as $property) {
      $name = explode('-', $property['name']);
      preg_match_all('%(?:href=")(?P<href>[^"]*)(?:[^>]*>)(?P<title>[^<]*)(?:</)%m', $property['demo'], $demolinks);
      $propdata = array(
        'name' => preg_replace('/^.*-([^-]+)$/m', '$1', $property['name']),
        'originalName' => $property['name'],
        'returnType' => $property['returnType'],
        'parent' => $property['parent'],
        'description' => $property['description'],
        'title' => $property['title'],
        'defaults' => $property['defaults'],
        'isParent' => $property['isParent'],
        'example' => array_combine($demolinks['title'], $demolinks['href']),
      );
      
      if($propdata['isParent'])
        $propdata['className'] = str_replace(' ', '', ucwords(str_replace('-', ' ', $property['name'])));
      //if ($property['isParent'] == true)
      //  $propdata['children'] = $this->getProperties($property['name']);
      if($property['returnType'] == 'Array<Object>')
        $propdata['returnType'] = $propdata['className'] . '[]';
      /*else if(!$property['isParent'] && empty($property['returnType']))
        continue;*/
      else if (!$property['isParent'])
        $propdata['returnType'] = $this->translateReturnType($property['returnType']);
      else if (isset($propdata['className']))
        $propdata['returnType'] = $propdata['className']; 
      $properties[] = $propdata;
    }
    return $properties;
  }
  
  public function translateReturnType($type) {
    $type = trim($type);
    if(strpos($type, '|') !== false) {
      $typeArray = explode('|', $type);
      $type = '';
      foreach($typeArray as $key => $singleType) {
        $typeArray[$key] = $this->translateReturnType($singleType);
      }
      $type = implode('|', $typeArray);
    } else {
      switch($type) {
        case 'String':
          $type = 'string';
          break;
        case 'Boolean':
          $type = 'bool';
          break;
        case 'Number':
          $type = 'number';
          break;
        case 'Array':
          $type = 'array';
          break;
        case 'Object':
          $type = 'object';
          break;
        case 'Color':
          $type = '\HighCharts\Color';
          break;
      }
    }
    return $type;
  }  
  
  public function getClassInfo($name) {
    
  }

  /** Processes description so that it can go in a docblock comment nicely * */
  public function processDescription($desc) {
    $desc = preg_replace('/\s|<[^>]+>/m', ' ', $desc);
    $desc = preg_replace('/(.{69}\S{0,10}\s)/m', "$1\n * ", $desc);
    $desc = preg_replace('/[ \t]{2,}/m', ' ', $desc);
    return $desc;
  }

  public function curlGetJSON($url) {
    if (!isset($_SESSION['curlcache']))
      $_SESSION['curlcache'] = array();
    $curlcache = &$_SESSION['curlcache'];
    if (array_key_exists($url, $curlcache))
      return $curlcache[$url];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_REFERER, "http://api.highcharts.com/highcharts");
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      'X-Requested-With: XMLHttpRequest',
      'Accept: application/json, text/javascript, */*; q=0.01'));
    $data = json_decode(curl_exec($ch), true);
    curl_close($ch);
    $curlcache[$url] = $data;
    return $data;
  }

}