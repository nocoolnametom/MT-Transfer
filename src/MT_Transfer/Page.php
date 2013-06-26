<?php
namespace MT_Transfer;

class Page
{
  protected $_domain;
  protected $_url;
  protected $_url_directories = '';
  protected $_div;
  protected $default_div = 'body';
  protected $_raw_page;
  protected $_body_dom;
  protected $_body_html;
  protected $_markdown;
  protected $_converter;
  protected $_link_preg = "/^([^:#]+)(#.*)?$/i";
  protected $_links = array();
  protected $_quiet_warnings = false;

  public function __construct($domain = null, $url = null, $div = null, $converter = null, $quiet_warnings = false, $link_preg = null)
  {
    $this->setDomain($domain)->setUrl($url)->setDiv($div)->setConverter($converter)->setLinkPreg($link_preg)->setQuietWarnings($quiet_warnings);
    if ($this->_url)
    {
      $this->assembleInternalLinks();
      $this->assembleMarkdown();
    }
  }

  public function setLinkPreg($link_preg)
  {
    if ($link_preg)
    {
      $this->_link_preg = $link_preg;
    }
    return $this;
  }

  public function setQuietWarnings($quiet_warnings)
  {
    $this->_quiet_warnings = $quiet_warnings;
    return $this;
  }

  public function setDomain($domain)
  {
    $this->_domain = $domain;
    return $this;
  }

  public function setUrl($url)
  {
    $this->_url = $url;
    $pattern = '~^(.+)/([^/]+)$~';
    preg_match($pattern, $this->_url, $matches);
    if (isset($matches[2]))
    {
      $this->_url = $matches[2];
      $this->_url_directories = trim($matches[1], '/') . '/';
    }
    return $this;
  }

  public function getUrl()
  {
    return $this->_url;
  }

  public function getUrlDirectories()
  {
    return $this->_url_directories;
  }

  public function setDefaultDiv($div)
  {
    $this->default_div = $div;
    return $this;
  }

  public function setDiv($div = null)
  {
    if (is_null($div))
    {
      $this->_div = $this->default_div;
    }
    else
    {
      $this->_div = $div;
    }
    return $this;
  }

  public function setConverter(\Markdownify\Converter $converter = null)
  {
    if (is_null($converter))
    {
      $this->_converter = new \Markdownify\Converter(true, false, false);
    }
    else
    {
      $this->_converter = $converter;
    }
    return $this;
  }

  public function getRawPage()
  {
    return ($this->_raw_page ? $this->_raw_page : $this->getRawPageFromInternet());
  }

  public function getRawPageFromInternet()
  {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, trim($this->_domain, '/') . '/' . $this->_url_directories . $this->_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $this->_raw_page = curl_exec($ch);
    curl_close($ch);
    return $this->_raw_page;
  }

  public function getBodyDom()
  {
    return ($this->_body_dom ? $this->_body_dom : $this->assembleBodyDom());
  }

  public function assembleBodyDom()
  {
    $this->_body_dom = \Sunra\PhpSimple\HtmlDomParser::str_get_html($this->getRawPage());
    return $this->_body_dom;
  }

  public function getBodyHTML()
  {
    return ($this->_body_html ? $this->_body_html : $this->assembleBodyHTML());
  }

  public function assembleBodyHTML()
  {
    $dom = $this->getBodyDom();
    if ($dom === false)
    {
      return $this->_body_html;
    }
    $page_elements = array();

    foreach($dom->find($this->_div) as $e)
    {
      $page_elements[] = $e->outertext;
    }

    $this->_body_html = str_replace(array("\r", "\n"), ' ', implode('', $page_elements));
    return $this->_body_html;
  }

  public function getMarkdown()
  {
    return ($this->_markdown ? $this->_markdown : $this->assembleMarkdown());
  }

  public function assembleMarkdown()
  {
    $body_html = $this->getBodyHTML();
    if ($this->_quiet_warnings)
    {
      $this->_markdown = @$this->_converter->parseString($body_html);
    }
    else
    {
      $this->_markdown = $this->_converter->parseString($body_html);
    }
    return $this->_markdown;
  }

  public function getInternalLinks()
  {
    return ($this->_links ? $this->_links : $this->assembleInternalLinks());
  }

  public function assembleInternalLinks()
  {
    $dom = $this->getBodyDom();
    if ($dom === false)
    {
      return $this->_links;
    }
    $links = $dom->find($this->_div . ' a');
    $page_elements = array();
    foreach($links as $link)
    {
      $href = $link->href;
      if (is_string($href) && preg_match($this->_link_preg, $href, $matches))
      {
        if (preg_match('/.*\.(pdf|gif|png|mp3|m3u|doc|jpg|jpeg)$/i', $href))
        {
          continue;
        }
        $href = $matches[1];
        $this->_links[$href] = $href;
      }
    }
    return $this->_links;
  }
}
