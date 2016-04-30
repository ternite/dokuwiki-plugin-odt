<?php
/**
 * Helper class to load standrad DokuWiki CSS files.
 * Adopted code from dw2pdf plugin by Andreas Gohr <andi@splitbrain.org>.
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     LarsDW223
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();


/**
 * Class helper_plugin_odt_dwcssloader
 */
class helper_plugin_odt_dwcssloader extends DokuWiki_Plugin {
    /**
     * @return array
     */
    function getMethods() {
        $result = array();
        $result[] = array(
                'name'   => 'load',
                'desc'   => 'Loads standard DokuWiki, plugin specific and format specific CSS files and templates. Includes handling of replacements and less parsing.',
                'params' => array('$plugin_name' => 'string',
                                  '$format' => 'string',
                                  '$template' => 'string'),
                'return' => array('All CSS styles' => 'string'),
                );
        return $result;
    }

    /**
     * Load all the style sheets and apply the needed replacements
     * @param $plugin_name
     * @param $format
     * @param $template
     * @return string
     */
    public function load($plugin_name, $format, $template, $usestyles) {
        //reusue the CSS dispatcher functions without triggering the main function
        define('SIMPLE_TEST', 1);
        require_once(DOKU_INC . 'lib/exe/css.php');

        // Always only use small letters in format
        $format = strtolower ($format);

        // prepare CSS files
        $files = array_merge(
            array(
                DOKU_INC . 'lib/styles/screen.css'
                    => DOKU_BASE . 'lib/styles/',
                DOKU_INC . 'lib/styles/print.css'
                    => DOKU_BASE . 'lib/styles/',
            ),
            css_pluginstyles('all'),
            $this->css_pluginFormatStyles($format, $usestyles),
            array(
                DOKU_PLUGIN . $plugin_name.'/conf/style.css'
                    => DOKU_BASE . 'lib/plugins/'.$plugin_name.'/conf/',
                DOKU_PLUGIN . $plugin_name.'/tpl/' . $template . '/style.css'
                    => DOKU_BASE . 'lib/plugins/'.$plugin_name.'/tpl/' . $template . '/',
                DOKU_PLUGIN . $plugin_name.'/conf/style.local.css'
                    => DOKU_BASE . 'lib/plugins/'.$plugin_name.'/conf/',
            )
        );
        $css = '';
        $css .= $this->get_css_for_filetypes();
        foreach($files as $file => $location) {
            $display = str_replace(fullpath(DOKU_INC), '', fullpath($file));
            $css_content = "\n/* XXXXXXXXX $display XXXXXXXXX */\n";
            $css_content = css_loadfile($file, $location);
            if ( strpos ($file, 'screen.css') !== false ) {
                $css .= "\n@media screen {\n" . $css_content . "\n}\n";
            } else if ( strpos ($file, 'style.css') !== false ) {
                $css .= "\n@media screen {\n" . $css_content . "\n}\n";
            } else if ( strpos ($file, $format.'.css') !== false ) {
                $css .= "\n@media print {\n" . $css_content . "\n}\n";
            } else if ( strpos ($file, 'print.css') !== false ) {
                $css .= "\n@media print {\n" . $css_content . "\n}\n";
            } else {
                $css .= $css_content;
            }
        }

        if(function_exists('css_parseless')) {
            // apply pattern replacements
            $styleini = css_styleini($template);
            $css = css_applystyle($css, $styleini['replacements']);

            // parse less
            $css = css_parseless($css);
        } else {
            // @deprecated 2013-12-19: fix backward compatibility
            $css = css_applystyle($css, DOKU_INC . 'lib/tpl/' . $template . '/');
        }

        return $css;
    }

    /**
     * Returns a list of possible Plugin Styles for format $format
     *
     * Checks for a $format.'.css', falls back to print.css
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     *
     * @param string $format
     * @return array
     */
    protected function css_pluginFormatStyles($format, $usestyles) {
        $list = array();
        $plugins = plugin_list();

        $temp = explode(',', $usestyles);
        $usestyle = array();
        foreach ($temp as $entry) {
            $usestyle [] = trim ($entry);
        }
        foreach($plugins as $p) {
            if(in_array($p, $usestyle)) {
                $list[DOKU_PLUGIN . $p ."/screen.css"] = DOKU_BASE . "lib/plugins/". $p ."/";
                $list[DOKU_PLUGIN . $p ."/style.css"] = DOKU_BASE . "lib/plugins/". $p ."/";
            }

            if(file_exists(DOKU_PLUGIN . $p ."/". $format .".css")) {
                $list[DOKU_PLUGIN . $p ."/". $format .".css"] = DOKU_BASE . "lib/plugins/". $p ."/";
            } else {
                $list[DOKU_PLUGIN . $p ."/print.css"] = DOKU_BASE . "lib/plugins/". $p ."/";
            }
        }
        return $list;
    }

    /**
     * Returns classes for file download links
     * (Adjusted from lib/exe/css.php: function css_filetypes())
     * 
     * @author Andreas Gohr <andi@splitbrain.org>
     */
    protected function get_css_for_filetypes() {
        $css = '';

        // default style
        $css .= '.mediafile {';
        $css .= ' background: transparent url('.DOKU_BASE.'lib/images/fileicons/file.png) 0px 1px no-repeat;';
        $css .= ' padding-left: 18px;';
        $css .= ' padding-bottom: 1px;';
        $css .= '}';

        // additional styles when icon available
        // scan directory for all icons
        $exts = array();
        if($dh = opendir(DOKU_INC.'lib/images/fileicons')){
            while(false !== ($file = readdir($dh))){
                if(preg_match('/([_\-a-z0-9]+(?:\.[_\-a-z0-9]+)*?)\.(png|gif)/i',$file,$match)){
                    $ext = strtolower($match[1]);
                    $type = '.'.strtolower($match[2]);
                    if($ext!='file' && (!isset($exts[$ext]) || $type=='.png')){
                        $exts[$ext] = $type;
                    }
                }
            }
            closedir($dh);
        }
        foreach($exts as $ext=>$type){
            $class = preg_replace('/[^_\-a-z0-9]+/','_',$ext);
            $css .= ".mf_$class {";
            $css .= '  background-image: url('.DOKU_BASE.'lib/images/fileicons/'.$ext.$type.')';
            $css .= '}';
        }

        return $css;
    }
}
