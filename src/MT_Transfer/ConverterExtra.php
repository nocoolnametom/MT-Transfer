<?php
namespace MT_Transfer;

/* This class only exists to fix some warnings that occur when running Markdownify.  Please
 * compare these functions to their counterparts in the Markdowniofy project to ensure that
 * everything is still okay.
 */

class ConverterExtra extends \Markdownify\ConverterExtra
{
    /**
     * handle <td> tags
     *
     * @param void
     * @return void
     */
    protected function handleTag_td()
    {
        if (!isset($this->table['col_widths'])) {
            $this->table['col_widths'] = array();
        }
        if ($this->parser->isStartTag) {
            $this->col++;
            if (!isset($this->table['col_widths'][$this->col])) {
                $this->table['col_widths'][$this->col] = 0;
            }
            $this->buffer();
        } else {
            $buffer = str_replace(array("\r\n", "\r", "\n"), "<br />", trim($this->unbuffer()));
            if (!isset($this->table['col_widths'][$this->col])) {
                $this->table['col_widths'][$this->col] = 0;
            }
            $this->table['col_widths'][$this->col] = max($this->table['col_widths'][$this->col], $this->strlen($buffer));
            $this->table['rows'][$this->row][$this->col] = $buffer;
        }
    }

    /**
     * properly pad content so it is aligned as whished
     * should be used with array_walk_recursive on $this->table['rows']
     *
     * @param string &$content
     * @param int $col
     * @return void
     */
    protected function alignTdContent(&$content, $col)
    {
        $alignment = isset($this->table['aligns'][$col]) ? $this->table['aligns'][$col] : null;
        switch ($alignment) {
            default:
            case 'left':
                $content .= str_repeat(' ', $this->table['col_widths'][$col] - $this->strlen($content));
                break;
            case 'right':
                $content = str_repeat(' ', $this->table['col_widths'][$col] - $this->strlen($content)) . $content;
                break;
            case 'center':
                $paddingNeeded = $this->table['col_widths'][$col] - $this->strlen($content);
                $left = floor($paddingNeeded / 2);
                $right = $paddingNeeded - $left;
                $content = str_repeat(' ', $left) . $content . str_repeat(' ', $right);
                break;
        }
    }

    /**
     * output link references (e.g. [1]: http://example.com "title");
     *
     * @param void
     * @return void
     */
    protected function flushStacked_a()
    {
        $out = false;
        foreach ($this->stack['a'] as $k => $tag) {
            if (!isset($tag['unstacked'])) {
                if (!$out) {
                    $out = true;
                    $this->out("\n\n", true);
                } else {
                    $this->out("\n", true);
                }
                $tag['linkID'] = isset($tag['linkID']) ? $tag['linkID'] : 0;
                $this->out(' [' . $tag['linkID'] . ']: ' . $tag['href'] . (isset($tag['title']) ? ' "' . $tag['title'] . '"' : ''), true);
                $tag['unstacked'] = true;
                $this->stack['a'][$k] = $tag;
            }
        }
    }

    /**
     * handle <a> tags
     *
     * @param void
     * @return void
     */
    protected function handleTag_a()
    {
        if ($this->parser->isStartTag) {
            $this->buffer();
            if (isset($this->parser->tagAttributes['title'])) {
                $this->parser->tagAttributes['title'] = $this->decode($this->parser->tagAttributes['title']);
            } else {
                $this->parser->tagAttributes['title'] = null;
            }
            $this->parser->tagAttributes['href'] = $this->decode(trim($this->parser->tagAttributes['href']));
            $this->stack();
        } else {
            $tag = $this->unstack();
            $buffer = $this->unbuffer();

            if (empty($tag['href']) && empty($tag['title'])) {
                # empty links... testcase mania, who would possibly do anything like that?!
                $this->out('[' . $buffer . ']()', true);

                return;
            }

            if ($buffer == $tag['href'] && empty($tag['title'])) {
                # <http://example.com>
                $this->out('<' . $buffer . '>', true);

                return;
            }

            $bufferDecoded = $this->decode(trim($buffer));
            if (substr($tag['href'], 0, 7) == 'mailto:' && 'mailto:' . $bufferDecoded == $tag['href']) {
                if (is_null($tag['title'])) {
                    # <mail@example.com>
                    $this->out('<' . $bufferDecoded . '>', true);

                    return;
                }
                # [mail@example.com][1]
                # ...
                #  [1]: mailto:mail@example.com Title
                $tag['href'] = 'mailto:' . $bufferDecoded;
            }
            # [This link][id]
            foreach ($this->stack['a'] as $tag2) {
                if ($tag2['href'] == $tag['href'] && $tag2['title'] === $tag['title']) {
                    $tag['linkID'] = (isset($tag2['linkID']) ? $tag2['linkID'] : 0);
                    break;
                }
            }
            if (!isset($tag['linkID'])) {
                $tag['linkID'] = count($this->stack['a']) + 1;
                array_push($this->stack['a'], $tag);
            }

            $this->out('[' . $buffer . '][' . $tag['linkID'] . ']', true);
        }
    }

    /**
     * handle <img /> tags
     *
     * @param void
     * @return void
     */
    protected function handleTag_img()
    {
        if (!$this->parser->isStartTag) {
            return; # just to be sure this is really an empty tag...
        }

        if (isset($this->parser->tagAttributes['title'])) {
            $this->parser->tagAttributes['title'] = $this->decode($this->parser->tagAttributes['title']);
        } else {
            $this->parser->tagAttributes['title'] = null;
        }
        if (isset($this->parser->tagAttributes['alt'])) {
            $this->parser->tagAttributes['alt'] = $this->decode($this->parser->tagAttributes['alt']);
        } else {
            $this->parser->tagAttributes['alt'] = null;
        }

        if (empty($this->parser->tagAttributes['src'])) {
            # support for "empty" images... dunno if this is really needed
            # but there are some testcases which do that...
            if (!empty($this->parser->tagAttributes['title'])) {
                $this->parser->tagAttributes['title'] = ' ' . $this->parser->tagAttributes['title'] . ' ';
            }
            $this->out('![' . $this->parser->tagAttributes['alt'] . '](' . $this->parser->tagAttributes['title'] . ')', true);

            return;
        } else {
            $this->parser->tagAttributes['src'] = $this->decode($this->parser->tagAttributes['src']);
        }

        # [This link][id]
        $link_id = false;
        if (!empty($this->stack['a'])) {
            foreach ($this->stack['a'] as $tag) {
                if ($tag['href'] == $this->parser->tagAttributes['src']
                    && $tag['title'] === $this->parser->tagAttributes['title']
                ) {
                    $link_id = (isset($tag['linkID']) ? $tag['linkID'] : 0);
                    break;
                }
            }
        } else {
            $this->stack['a'] = array();
        }
        if (!$link_id) {
            $link_id = count($this->stack['a']) + 1;
            $tag = array(
                'href' => $this->parser->tagAttributes['src'],
                'linkID' => $link_id,
                'title' => $this->parser->tagAttributes['title']
            );
            array_push($this->stack['a'], $tag);
        }

        $this->out('![' . $this->parser->tagAttributes['alt'] . '][' . $link_id . ']', true);
    }

    /**
     * handle <table> tags
     *
     * @param void
     * @return void
     */
    protected function handleTag_table()
    {
        if ($this->parser->isStartTag) {
            # check if upcoming table can be converted
            if ($this->keepHTML) {
                if (preg_match($this->tableLookaheadHeader, $this->parser->html, $matches)) {
                    # header seems good, now check body
                    # get align & number of cols
                    preg_match_all('#<th(?:\s+align=("|\')(left|right|center)\1)?\s*>#si', $matches[0], $cols);
                    $regEx = '';
                    $i = 1;
                    $aligns = array();
                    foreach ($cols[2] as $align) {
                        $align = strtolower($align);
                        array_push($aligns, $align);
                        if (empty($align)) {
                            $align = 'left'; # default value
                        }
                        $td = '\s+align=("|\')' . $align . '\\' . $i;
                        $i++;
                        if ($align == 'left') {
                            # look for empty align or left
                            $td = '(?:' . $td . ')?';
                        }
                        $td = '<td' . $td . '\s*>';
                        $regEx .= $td . $this->tdSubstitute;
                    }
                    $regEx = sprintf($this->tableLookaheadBody, $regEx);
                    if (preg_match($regEx, $this->parser->html, $matches, null, strlen($matches[0]))) {
                        # this is a markdownable table tag!
                        $this->table = array(
                            'rows' => array(),
                            'col_widths' => array(),
                            'aligns' => $aligns,
                        );
                        $this->row = 0;
                    } else {
                        # non markdownable table
                        $this->handleTagToText();
                    }
                } else {
                    # non markdownable table
                    $this->handleTagToText();
                }
            } else {
                $this->table = array(
                    'rows' => array(),
                    'col_widths' => array(),
                    'aligns' => array(),
                );
                $this->row = 0;
            }
        } else {
            # finally build the table in Markdown Extra syntax
            $separator = array();
            $this->table['aligns'] = isset($this->table['aligns']) ? $this->table['aligns'] : array();
            # seperator with correct align identifikators
            foreach ($this->table['aligns'] as $col => $align) {
                if (!$this->keepHTML && !isset($this->table['col_widths'][$col])) {
                    break;
                }
                $left = ' ';
                $right = ' ';
                switch ($align) {
                    case 'left':
                        $left = ':';
                        break;
                    case 'center':
                        $right = ':';
                        $left = ':';
                    case 'right':
                        $right = ':';
                        break;
                }
                array_push($separator, $left . str_repeat('-', $this->table['col_widths'][$col]) . $right);
            }
            $separator = '|' . implode('|', $separator) . '|';

            $rows = array();
            # add padding
            array_walk_recursive($this->table['rows'], array(&$this, 'alignTdContent'));
            $header = array_shift($this->table['rows']);
            $header = is_null($header) ? array() : $header;
            array_push($rows, '| ' . implode(' | ', $header) . ' |');
            array_push($rows, $separator);
            foreach ($this->table['rows'] as $row) {
                array_push($rows, '| ' . implode(' | ', $row) . ' |');
            }
            $this->out(implode("\n" . $this->indent, $rows));
            $this->table = array();
            $this->setLineBreaks(2);
        }
    }
}