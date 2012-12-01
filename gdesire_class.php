<?php

class GDesire{
    
    private $_src = null;
    private $_image = null;
    private $_hasTrasparency = false;


    function __destruct()
    {        
        if($this->_image) imagedestroy($this->_image);
    }

    function __construct($src = null, $image = null)
    {   

        if(!$image && $src){
            //method chain return self initialized
            return $this->load($src);            
        }
        
    }


    public function status()
    {
        return array($this->_src,$this->_image,$this->_hasTrasparency);         
    }


    /*
     *  load()
     *  Carica una immagine dato il path di un file e 
     *  restituisce una GD Image Resource
     *  @parameters: 
     *      string src
     *  @returns
     *      GD Image Resource
     *
     */

    public function load($src) {

        /*var_dump($src);die();*/

        if(!file_exists($src)) throw new Exception("File \"$src\" NON ESISTENTE");
        
        /*var_dump('load');die();*/

        $info = getimagesize($src);
        if( !$info ) return false;
        
        switch( $info['mime'] ) {
            
            case 'image/gif':
                $image = imagecreatefromgif($src);
            break;
            
            case 'image/jpeg':
                $image = imagecreatefromjpeg($src);
            break;
            
            case 'image/png':
                $image = imagecreatefrompng($src);
            break;
            
            default:
                // Unsupported image type
                throw new Exception("File \"$src\" NON SUPPORTATO");
                return false;
            break;
            
        }
        //init
        $this->_src = $src;
        $this->_image = $image;
        $this->_hasTrasparency = in_array($info['mime'], array('image/gif','image/png')) ? $this->_detectTrasparency() : false;
        if($this->_hasTrasparency)$this->_preserveTrasparency();

        return $this;
    }


    /*
     *  flush()
     *  Ritorna l'attuale GD Resource Image
     *  @parameters: 
     *      void
     *  @returns
     *      ImageResource *image   
     *
     */

    public function flush()
    {   
        return $this->_image;
    }




    public function kill($deleteSource = false)
    {
        imagedestroy($this->_image);

        $this->_image = $this->_src = null;
        $this->_hasTrasparency = false;

        //if($deleteSource)unlink($this->_src);

        return;
    }


    /*
     *  save()
     *  Salva un'immagine in file data una GD Image Resource
     *  @parameters: 
     *      ImageResource *image
     *  @returns
     *      BOOL true : success / false : fail
     *
     */
    public function save($filepath = null, $type = null, $quality = null)
    {   

        $image = $this->_image;

        list($dir,$base,$ext,$filename) = array_values(pathinfo($this->_src));

        $filename = ($filepath) ? $filepath : "$dir/$filename" . "_Pimage.$ext";        

        $type = $type ? $type : $ext;

        switch( $type ) {            
            case 'gif':
            case 'image/gif':
                $check = imagegif($image, $filename);
            break;
            
            case 'jpeg': case 'jpg':
            case 'image/jpeg':
                if( $quality == null ) $quality = 85;
                if( $quality < 0 ) $quality = 0;
                if( $quality > 100 ) $quality = 100;
                $check = imagejpeg($image, $filename, $quality);
            break;
            
            case 'png':
            case 'image/png':
                if( $quality == null ) $quality = 9;
                if( $quality > 9 ) $quality = 9;
                if( $quality < 1 ) $quality = 0;
                $check = imagepng($image, $filename, $quality);
            break;
            
            default:
                // Unsupported image type
                $check = false;
            break;
            
        }

        /*var_dump($check);*/

        return $check ? $this : false;
    }   


    public function resize($newWidth, $newHeight)
    {
        $image = $this->_image;

        $canvasW = $newWidth;
        $canvasH = $newHeight;

        $width = imagesx($image);
        $height = imagesy($image);        


        //calcolo delle dimensioni di resize
        list($w, $h) = array_values($this->_getPreservedSizes($canvasW, $canvasH, true ));

        //resized è l'immagine ridimensionata
        $resized = imagecreatetruecolor($w, $h);
        if($this->_hasTrasparency)$this->_preserveTrasparency($resized);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $w, $h, $width, $height);

        $this->_image = $resized;

        return $this;
    }


    public function pasteInsideCanvas($canvasW, $canvasH, $marginX = 'auto', $marginY = 'auto', $canvasColor = null)
    {
        $image = $this->_image;
        $canvas = imagecreatetruecolor($canvasW, $canvasH);

        if($this->_hasTrasparency){             
            imagefill($canvas, 0, 0, imagecolorallocatealpha( $canvas, 0, 0, 0, 127 )); //riempi il canvas di colore trasparente        
            imagealphablending( $canvas, false ); //preserva trasparenza del canvas
            imagesavealpha( $canvas, true );  //preserva trasparenza del canvas
        }

        $w = imagesx($image);
        $h = imagesy($image);

        $mX = ($marginX == "auto") ? ($canvasW - $w) / 2 : $marginX; //calcola margine simmetrico orizzontale
        $mY = ($marginY == "auto") ? ($canvasH - $h) / 2 : $marginY; //calcola margine simmetrico verticale

        imagecopy($canvas, $image, $mX, $mY, 0, 0, $w, $h);

        imagedestroy($image);

        $this->_image = $canvas;

        return $this;       

    }




    public function greize($contrast = -10)
    {   
        $image = $this->_image;

        imagefilter($image, IMG_FILTER_GRAYSCALE);
        imagefilter($image, IMG_FILTER_CONTRAST, $contrast);

        $this->_image = $image;

        return $this;

    }


    public function logSizes()
    {
        print_r(array(imagesx($this->_image), imagesy($this->_image)));
        return $this;
    }






    public function asciiArt($resolution = 2, $filled = '°', $void = '.', $newLine = '<br/>')
    {   
        $image = $this->_image;

        $width = imagesx($image);
        $height = imagesy($image);

        $str = '';

        for($y = 1; $y < $height; $y += $resolution){
            for($x = 1; $x < $width; $x += $resolution){
                list($r,$g,$b,$a) = array_values(imagecolorsforindex($image, @imagecolorat($image, $x, $y)));              
                $str .= (!($a == 127 || ($r == $g && $g == $b && $b == 255))) ? $filled : $void;
            }
            $str .= $newLine;
        }

        return $str;

    }



    


    /*
     *  intelliCrop
     *  Toglie l'area vuota esterna di una immagine png 
     *  @parameters: 
     *      ImageResource *image
     *  @returns
     *      ImageResource *image   
     *
     */
    public function intelliCrop()
    {   
        $image = $this->_image;

        $width = imagesx($image);
        $height = imagesy($image);

        list($x0, $y0, $x1, $y1) = $this->_detectRectangle($image);

        //crea un canvas trasparente
        $canvas = imagecreatetruecolor($x1 - $x0, $y1 - $y0);
        
        if($this->_hasTrasparency){
            imagefill($canvas, 0, 0, imagecolorallocatealpha( $canvas, 0, 0, 0, 127 )); //riempi il canvas di colore trasparente
            $this->_preserveTrasparency($canvas);
        }

        //Cropping nel canvas
        imagecopyresampled($canvas, $image, 0, 0, $x0, $y0, $x1 - $x0, $y1 - $y0, $x1 - $x0, $y1 - $y0);
        imagedestroy ($image);

        $this->_image = $canvas;

        return $this;
    }




    /*
     *  intelliCrop PRIVATA
     *  Determina in modo intelligente la porzione di immagine non vuota
     *  (con colori diversi dal bianco o dal trasparente)
     *  e restituisce le coordinate
     *
     *  @parameters: 
     *      ImageResource *image
     *  @returns
     *      Array {x0,x1,y0,y1}   
     *
     */
    private function _detectRectangle($image)
    {
        $width = imagesx($image);
        $height = imagesy($image);

        $minX = $width;
        $minY = $height;
        $maxX = $maxY = 0;
        $margin = 3;

        for($y = 1; $y < $height; $y++){
            for($x = 1; $x < $width; $x++){
                list($r,$g,$b,$a) = array_values(imagecolorsforindex($image, @imagecolorat($image, $x, $y)));
                if(!($a == 127 || ($r == $g && $g == $b && $b == 255))){
                    if($x < $minX)$minX = $x;
                    if($y < $minY)$minY = $y;
                    if($x > $maxX)$maxX = $x;
                    if($y > $maxY)$maxY = $y;
                }
            }
        }
        $minX = (($minX - $margin) <= 0) ? $minX : $minX - $margin; 
        $minY = (($minY - $margin) <= 0) ? $minY : $minY - $margin; 
        $maxX = (($maxX + $margin) >= $width) ? $maxX : $maxX + $margin; 
        $maxY = (($maxY + $margin) >= $height) ? $maxY : $maxY + $margin; 
        
        return array($minX, $minY, $maxX, $maxY);
    }


    /*
     *  _detectTrasparency PRIVATA
     *  Determina se l'immagine ha della trasparenza
     *
     *  @parameters: 
     *      void
     *  @returns:
     *      BOOL true/false   
     *
     */

    private function _detectTrasparency()
    {
        $image = $this->_image; 

        for($y = 1; $y < imagesy($image); $y++){
            for($x = 1; $x < imagesx($image); $x++){
                list($r,$g,$b,$a) = array_values(imagecolorsforindex($image, @imagecolorat($image, $x, $y)));
                if($a > 0)return true;
            }
        }
        return false;
    }



    /*
     *  _preserveTrasparency PRIVATA
     *  Preserva la trasparenza della immagine in memoria
     *
     *  @parameters: 
     *      [*ImgResource] (optional)
     *  @returns:
     *      void   
     *
     */

    private function _preserveTrasparency($imgResource = null)
    {
        $image = $imgResource ? $imgResource : $this->_image;
        imagealphablending($image, false); // preserva trasparenza
        imagesavealpha( $image, true );  //preserva trasparenza
    }


    private function _fill($imgResource, $color)
    {
        /*isHex ?

        isArray ?*/
        list($r,$g,$b,$a) = $rgbaArray;
    }


    /*
     *  _hasTrasparency PRIVATA
     *  Determina se l'immagine ha della trasparenza
     *
     *  @parameters: 
     *      void
     *  @returns:
     *      void   
     *
     */    

    private function _getPreservedSizes($maxWidth, $maxHeight, $alwaysUpscale = true )
    {
        
        $origWidth = imagesx($this->_image);
        $origHeight = imagesy($this->_image);

        // Check if the image we're grabbing is larger than the max width or height or if we always want it resized
        if ( $alwaysUpscale || $origWidth > $maxWidth || $origHeight > $maxHeight )
        {   
            // it is so let's resize the image intelligently
            // check if our image is landscape or portrait
            if ( $origWidth > $origHeight )
            {
                    // target image is landscape/wide (ex: 4x3)
                    $newWidth = $maxWidth;
                    $ratio = $maxWidth / $origWidth;
                    $newHeight = floor($origHeight * $ratio);
                    // make sure the image wasn't heigher than expected
                    if ($newHeight > $maxHeight)
                    {
                            // it is so limit by the height
                            $newHeight = $maxHeight;
                            $ratio = $maxHeight / $origHeight;
                            $newWidth = floor($origWidth * $ratio);
                    }
            }
            else
            {
                    // target image is portrait/tall (ex: 3x4)
                    $newHeight = $maxHeight;
                    $ratio = $maxHeight / $origHeight;
                    $newWidth = floor($origWidth * $ratio);
                    // make sure the image wasn't wider than expected
                    if ($newWidth > $maxWidth)
                    {
                            // it is so limit by the width
                            $newWidth = $maxWidth;
                            $ratio = $maxWidth / $origWidth;
                            $newHeight = floor($origHeight * $ratio);
                    }
            }
        }
        // it's not, so just use the current height and width
        else
        {
            $newWidth = $origWidth;
            $newHeight = $origHeight;
        }


        return array("width"=>$newWidth, "height"=>$newHeight);
    }
    
}
?>
