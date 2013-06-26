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
$content_div_id = 'div#' . $config->get('content_div_id', 'layout_info');
$site_map = $config->get('site_map', 'website_information');
$urls = $config->get('extra_urls', 'website_information');

$finished_urls = array();

$site_map_page = new MT_Transfer\Page($domain, $site_map, $content_div_id, $converter, $config->get('hide_warnings', 'output'));

$urls = array_merge($urls, $site_map_page->getInternalLinks());

$page_elements = array();
while(count($urls))
{
  $url = array_pop($urls);
  if (isset($finished_urls[$url]))
  {
    continue;
  }
  if (preg_match('/.*Templates.*/', $url))
  {
    continue;
  }
  $finished_urls[$url] = $url;
  $extra_url_page = new MT_Transfer\Page($domain, $url, $content_div_id, $converter, $config->get('hide_warnings', 'output'));
  $urls = array_merge($urls, $extra_url_page->getInternalLinks());
  $markdown = $extra_url_page->getMarkdown();

  $new_filename = trim($config->get('md_directory', 'output'), '/') . '/'
                . $extra_url_page->getUrlDirectories()
                . preg_replace('/(.*)\.html?/i', '$1' , $extra_url_page->getUrl()) . '.md';
  file_put_contents($new_filename, $markdown);
  if (!$config->get('quiet', 'output'))
  {
    echo "\n" . $new_filename . ' written...';
  }
}
if (!$config->get('quiet', 'output'))
{
  echo "\n";
}