<?php
if ( !defined('K_COUCH_DIR') ) die(); // cannot be loaded directly

//Timestamps
//define('MINIFY_TIMESTAMP_QUERYSTRING', 1);
//define('MINIFY_TIMESTAMP_FILENAME', 1);// ** Requires a rewrite rule in .htaccess **
        //RewriteRule ^(.+)\^([\d-]+)\^\.(js|css)$ $1 [L]

function minify_css($css){
    //Remove comments
    $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
    //Remove tabs, spaces, and line breaks
    $css = preg_replace(array('/\s{2,}/', '/[\t\n]/'), '', $css);
    //whitespace around punctuation
    $css = preg_replace('/\s*([:;{}])\s*/', '$1', $css);
    //final semicolon
    $css = preg_replace('/;}/', '}', $css);
    return $css;
}

function minify_timestamp( $link, $file ){ 
    //versioning technique by @trendoman
    //https://www.couchcms.com/forum/viewtopic.php?f=8&t=10644
    $fileDetails = pathinfo( $link );
    $dirname = strtolower( $fileDetails["dirname"] );
    $filename = strtolower( $fileDetails["filename"] );
    $extension = strtolower( $fileDetails["extension"] );
    $output = $dirname . "/" . $filename . "." . $extension . "^" . filemtime( $file ) . "^." . $extension;
    return $output;
}

class MinifyJsCss{
    static function minify_js_css( $params, $node ){
           
        // sanitize params
        $filetype = strtolower(trim($params[0]['rhs']));
        if($filetype != 'css' && $filetype != 'js'){die("ERROR: Tag \"".$node->name."\" - Must specify either 'css' or 'js'.");}
        $filepath = trim(str_replace( K_SITE_URL, "", $params[1]['rhs'] ));
        $output_file = ($filepath) ? K_SITE_DIR . $filepath : '';
        $output_link = ($filepath) ? K_SITE_URL . $filepath : '';
        $i=0;
        foreach($params as $attribs){
            if($i > 1){
                $tag_attributes .= ' ' . $attribs['lhs'] . '="' . $attribs['rhs'].'"';
            }
            $i++;
        }
        //Add listed files to an array and sanitize
        foreach( $node->children as $child ){
            $file_list .= $child->get_HTML();
        }
        if($file_list){
            //Split on whitespace and commas
            $files = preg_split('/[\s,+]/', $file_list, -1, PREG_SPLIT_NO_EMPTY);
            //strip away k_site_link if present
            $files = str_replace(K_SITE_URL, '', $files);
            //sanitize file path
            foreach( $files as &$file ){
                $file = str_replace(K_SITE_URL, '', $file);
                $file = ltrim($file, '/');
            }
        }else{
            die("ERROR: Tag \"".$node->name."\" - No files were listed.");
        }
        if(MINIFY_TIMESTAMP_FILENAME===1){$output_link = minify_timestamp($output_link, $output_file);}
        $css_tag = '<link rel="stylesheet" href="'.$output_link;
        if(MINIFY_TIMESTAMP_QUERYSTRING===1){$css_tag .= '?'.filemtime($output_file);}
        $css_tag .= '"'.$tag_attributes.' />';
        $js_tag = '<script type="text/javascript" src="'.$output_link;
        if(MINIFY_TIMESTAMP_QUERYSTRING===1){$js_tag .= '?'.filemtime($output_file);}
        $js_tag .= '"'.$tag_attributes.'></script>';
        
        //compare modification dates to output file
        if($output_file){
            foreach($files as $item){
                if( filemtime(K_SITE_DIR . $item) > filemtime($output_file) ){
                    $modified = 1; break;
                }
            }
            //No new modifications. Render 'link' or 'script' tag. Done.
            if (!$modified && $filetype == 'css'){
                return $css_tag;
                }
            if (!$modified && $filetype == 'js'){
                return $js_tag;
            }
        }
        
        //Combine files
        foreach($files as $item){ 
            $content .= file_get_contents(K_SITE_URL . $item);
        }
        //minify combined files
        if ($filetype == 'css'){ $output = minify_css($content); }
        if ($filetype == 'js'){ 
            require_once( K_COUCH_DIR.'addons/minify-js-css/JShrink.php' ); 
            $output = \JShrink\Minifier::minify($content); 
        }
        
        //No output file. Embed output on page. Done.
        if(!$output_file){ 
            if ($filetype == 'css'){ return '<style' . $tag_attributes . '>' . $output . '</style>'; }
            if ($filetype == 'js'){ return '<script type="text/javascript"' . $tag_attributes . '>' . $output . '</script>'; }
        }
        
        //Create new output file. Render tag. Done.
        file_put_contents($output_file, $output);
        if ($filetype == 'css'){
                return $css_tag;
        }
        if ($filetype == 'js'){
                return $js_tag;
        }
    }
}
$FUNCS->register_tag( 'minify', array('MinifyJsCss', 'minify_js_css') );
