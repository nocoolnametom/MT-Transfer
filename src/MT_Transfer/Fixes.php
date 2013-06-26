<?php

namespace MT_Transfer;

class Fixes
{
  protected static $_patterns = array(
    '8 leading spaces' => array(
      '/\n\s{8,}([^\s])/s' => "\n\n$1"),
    'spaces on empty lines' => array(
      '/\n\s+\n/s' => "\n\n"),
    '8 leading spaces and then five quotes' => array(
      '/\n\s{8}>\s>\s>\s>\s>\s>\s?/s' => "\n\n"),
    '4 leading spaces' => array(
      '/\n\s{4}([^\s])/' => "\n$1"),
    'more than two empty lines' => array(
      '/(\n\s*){3,}/' => "\n\n"),
    'new line on a quote block' => array(
      '/\n\n>\s+\n>/' => "\n>\n>"),
    'horizontal rules' => array(
      '(\\___){2,}' => '---------------------------------------')
    );

  protected static function listOfPatterns($pattern_name)
  {
    $patterns = static::$_patterns;
    return (isset($patterns[$pattern_name]) ? $patterns[$pattern_name] : array());
  }

  protected static function translateNamesToPatterns($needed)
  {
    $output = array();
    foreach($needed as $pattern_name)
    {
      $output = array_merge($output, static::listOfPatterns($pattern_name));
    }
    return $output;
  }

  protected static function assemblePatternsForAll()
  {
    $patterns = array(
      'spaces on empty lines',
      '8 leading spaces and then five quotes',
      '8 leading spaces',
      'more than two empty lines',
      'new line on a quote block',
    );

    return static::translateNamesToPatterns($patterns);
  }

  protected static function assembleSpecificPatternsForFile($filename)
  {
    $patterns = array(
      'moretempleexp.htm' => array(
        '4 leading spaces',
      ),
    );
    $needed = (isset($patterns[$filename]) ? $patterns[$filename] : array());

    return static::translateNamesToPatterns($needed);
  }

  protected static function assemblePatternsForGroupsOfFiles($filename)
  {
    $patterns = array(
      '8 leading spaces' => array(
        'book-of-mormon-problems.htm',
        'book-of-abraham-issues.htm',
      ),
    );

    $needed = array();
    foreach($patterns as $pattern_name => $files)
    {
      if (in_array($filename, $files))
      {
        $needed[] = $pattern_name;
      }
    }

    return static::translateNamesToPatterns($needed);
  }

  public static function getFixedMarkdown(Page $page)
  {
    $markdown = $page->getMarkdown();

    $patterns = static::$_patterns;

    foreach(static::assemblePatternsForAll() as $pattern => $replacement)
    {
      $markdown = preg_replace($pattern, $replacement, $markdown);
    }

    foreach(static::assemblePatternsForGroupsOfFiles($page->getUrl()) as $pattern => $replacement)
    {
      $markdown = preg_replace($pattern, $replacement, $markdown);
    }

    foreach(static::assembleSpecificPatternsForFile($page->getUrl()) as $pattern => $replacement)
    {
      $markdown = preg_replace($pattern, $replacement, $markdown);
    }

    return $markdown;
  }
}