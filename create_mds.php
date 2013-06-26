<?php
require 'vendor/autoload.php';

$config = new MT_Transfer\Config('config.ini');

if ($config->get('use_markdown_extra', 'options')) {
  $converter = new Markdownify\ConverterExtra(true, false, false);
} else {
  $converter = new Markdownify\Converter(true, false, false);
}

$domain = rtrim('http://' . $config->get('subdomain', 'website_information')
          . $config->get('domain', 'website_information'), '/') . '/';

$site_map = $config->get('site_map', 'website_information');

$extra_urls = $config->get('extra_urls', 'website_information');

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $domain . $site_map);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$site_map_page = curl_exec($ch);

$dom = Sunra\PhpSimple\HtmlDomParser::str_get_html($site_map_page);

$page_elements = array();
foreach($extra_urls as $url)
{
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $domain . $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  $url_page = curl_exec($ch);

  $dom = Sunra\PhpSimple\HtmlDomParser::str_get_html($url_page);

  $page_elements = array();

  foreach($dom->find('div#' . $config->get('content_div_id', 'layout_info')) as $e)
  {
    $page_elements[] = $e->outertext;
  }

  $page_text = str_replace(array("\r", "\n"), ' ', implode('', $page_elements));

  $markdown = $converter->parseString($page_text);

  file_put_contents(trim($config->get('md_directory', 'output'), '/') . '/' . $url . '.md', $markdown);
}