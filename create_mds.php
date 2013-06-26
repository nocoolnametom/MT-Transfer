<?php
require 'vendor/autoload.php';

$config = new MT_Transfer\Config('config.ini');

if ($config->get('use_markdown_extra', 'options')) {
  $converter = new MT_Transfer\ConverterExtra(false, false, false);
} else {
  $converter = new Markdownify\Converter(false, false, false);
}

$domain = rtrim('http://' . $config->get('subdomain', 'website_information')
          . $config->get('domain', 'website_information'), '/') . '/';
$content_div_id = 'div#' . $config->get('content_div_id', 'layout_info');
$site_map = $config->get('site_map', 'website_information');
$urls = $config->get('extra_urls', 'website_information');
$no_warn = $config->get('hide_warnings', 'output');

$finished_urls = array();

$site_map_page = new MT_Transfer\Page($domain, $site_map, $content_div_id, $converter, $no_warn);

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
  $extra_url_page = new MT_Transfer\Page($domain, $url, $content_div_id, $converter, $no_warn);
  $page_title = $extra_url_page->getTitleTag();
  if ($page_title == '404 Not Found')
  {
    continue;
  }
  $urls = array_merge($urls, $extra_url_page->getInternalLinks());
  $markdown = MT_Transfer\Fixes::getFixedMarkdown($extra_url_page);

  $directory = rtrim($config->get('md_directory', 'output'), '/') . '/'
             . $extra_url_page->getUrlDirectories();

  $new_filename = $directory . preg_replace('/(.*)\.html?/i', '$1' , $extra_url_page->getUrl())
                . '.md';
  if (!is_dir($directory)) {
    mkdir($directory);
  }
  $header = $page_title ? '<!--|'.$page_title."|-->\n" : '';
  file_put_contents($new_filename, $header.$markdown);
  if (!$config->get('quiet', 'output'))
  {
    echo "\n" . $new_filename . ' written...';
  }
}
if (!$config->get('quiet', 'output'))
{
  echo "\n";
}