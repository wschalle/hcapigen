<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
include 'vendor/autoload.php';
include 'ApiGenerator.php';

$apigen = new HCApiGenerator();
$apigen->generate('HighCharts\ChartOptions');