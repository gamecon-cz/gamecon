<?php

/** Searches an array for all specified keys
 *  @return bool true if all exist false otherwise */
function array_keys_exist($keys,$search)
{
  //if(is_array($search) && is_array($keys))
    foreach($keys as $key)
      if(!array_key_exists($key,$search))
        return false;
  return true;
}

/** Flattens array in manner $pre.$element.$post for all elements, separated by $sep */
function array_flat($pre, $array, $post = '', $sep = '') {
  $out = '';
  foreach($array as $e) $out .= $pre.$e.$post;
  return $out;
}

/**
 * Iterates trough array and prints combined output returned by function in
 * each iteration
 */
function array_uprint($array, callable $func, $sep = '') {
  $out = '';
  foreach($array as $e) {
    $out .= $func($e) . $sep;
  }
  if($sep) {
    $out = substr($out, 0, -strlen($sep));
  }
  return $out;
}

function reload() {
  header('Refresh: 0', true, 303);
  exit;
}

/**
 * Ends current script execution and reloads page to http referrer.
 * @param string $to alternative location to go to instead of referrer
 */
function back($to=null) {
  if($to)
    header('Location: '.$to, true, 303);
  elseif(isset($_SERVER['HTTP_REFERER']))
    header('Location: '.$_SERVER['HTTP_REFERER'], true, 303);
  elseif($_SERVER['REQUEST_METHOD'] != 'GET')
    header('Location: '.$_SERVER['REQUEST_URI'], true, 303);
  else
    header('Location: /', true, 303);

  exit();
}

function get($name)
{
  if(isset($_GET[$name])) return $_GET[$name];
  else return null;
}

/**
 * Options parsing, returns assoc. array with options.
 * if option in $default has key and value, then it's default,
 * if in $default is just value, it is assumed mandatory argument
 * and exception is thrown, if not set in $actual
 */
function opt($actual, $default) {
  $opt = [];
  foreach($default as $key => $val) {
    if(is_numeric($key)) {
      if(array_key_exists($val, $actual))
        $opt[$val] = $actual[$val];
      else
        throw new BadFunctionCallException('key "'.$val.'" in options missing');
    } else {
      if(array_key_exists($key, $actual))
        $opt[$key] = $actual[$key];
      else
        $opt[$key] = $val;
    }
  }
  return $opt;
}

function post($name, $field = null)
{
  if(!$field && isset($_POST[$name]))         return $_POST[$name];
  if($field && isset($_POST[$name][$field]))  return $_POST[$name][$field];
  return null;
}

/** Returns temporary filename for uploaded file or '' if none */
function postFile($name)
{
  if(isset($_FILES[$name]['tmp_name'])) return $_FILES[$name]['tmp_name'];
  else return '';
}

/**
 * Converts '"~"' to '"([^"])+"'
 */
function preg_quote_wildcard($re) {
  $re = preg_quote($re);
  $re = preg_replace('@~(\\\\?.)@', '([^$1]*)$1', $re);
  return $re;
}

/**
 * Returns random hexadecimal number in with $chars number of characters (ie.
 * half $chars bytes)
 */
function randHex($chars)
{
  if(!($chars<=32 && $chars>=0))
    throw new Exception('maximum characters is 32 so far.');
  return substr(md5(mt_rand()),0,$chars);
}


/**
 * Convert snake_case to camelCase
 */
function snakeToCamel($str) {
  $str = ucwords($str, '-');
  $str = str_replace('-', '', $str);
  $str = lcfirst($str);
  return $str;
}


/**
 * Returns part of string after rightmost occurence of delimiter
 */
function strrafter($string, $delimiter)
{
  $pos = strrpos($string, $delimiter);
  return substr($string, $pos+1);
}

/**
 * Returns part of string after rightmost occurence of delimiter
 */
function strrbefore($string, $delimiter)
{
  $pos = strrpos($string, $delimiter);
  return substr($string, 0, $pos);
}


/**
 * Switches rows and columns
 */
function tabArrayR($ai)
{
  $ao=[];
  $ih=count($ai);
  $iw=count($ai[0]);
  for($ic=0;$ic<$iw;$ic++)
    for($ir=0;$ir<$ih;$ir++)
      $ao[$ic][$ir]=$ai[$ir][$ic];
  return $ao;
}

/**
 * Returns HTML formatted table from array
 */
function tabHtml($tab)
{
  $tabOut="<table>\n";
  $tabOut.="  <tr>\n    <th>".implode("</th>\n    <th>",$tab[0])."</th>\n  </tr>\n";
  for($i=1;$i<count($tab);$i++)
    $tabOut.="  <tr>\n    <td>".implode("</td>\n    <td>",$tab[$i])."</td>\n  </tr>\n";
  $tabOut.="</table>\n\n";
  return $tabOut;
}

/**
 * Returns HTML formatted table from db answer
 */
function tabMysql($a)
{
  $tabOut="<table>\n";
  if(!$r=mysqli_fetch_assoc($a))
    return '';
  $tabOut.="  <tr>\n    <th>".implode("</th>\n    <th>",array_keys($r))."</th>\n  </tr>\n";
  $tabOut.="  <tr>\n    <td>".implode("</td>\n    <td>",$r)."</td>\n  </tr>\n";
  while($r=mysqli_fetch_row($a))
    $tabOut.="  <tr>\n    <td>".implode("</td>\n    <td>",$r)."</td>\n  </tr>\n";
  $tabOut.="</table>\n\n";
  return $tabOut;
}

/**
 * Returns table array from mysql answer
 */
function tabMysqlArray($a)
{
  $r=mysqli_fetch_assoc($a);
  $oa[]=array_keys($r);
  $oa[]=array_values($r);
  while($r=mysqli_fetch_row($a))
    $oa[]=$r;
  return $oa;
}

/**
 * Returns HTML formatted table from db answer, mirrored
 */
function tabMysqlR($a)
{
  return tabHtml(tabArrayR(tabMysqlArray($a)));
}
