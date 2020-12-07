<?php

namespace wiggels\phPDF;

define('PHPDF_VERSION','0.1');

class PDF {

    protected int $currentPage;          // current page number
    protected int $currentObject;        // current object number
    protected array $offsets;            // array of object offsets
    protected string $buffer;            // buffer holding in-memory PDF
    protected array $pages;              // array containing pages
    protected int $state;                // current document state
    protected bool $compress;            // compression flag
    protected float $scale;                  // scale factor (number of points in user unit)
    protected string $defaultOrientation;// default orientation
    protected string $currentOrientation;// current orientation
    protected array $standardPageSizes;  // standard page sizes
    protected array $defaultPageSize;    // default page size
    protected array $currentPageSize;    // current page size
    protected int $currentRotation;      // current page rotation
    protected array $pageInfo;           // page-related data
    protected float $currentWidthPoints; // width of current page in points
    protected float $currentHeightPoints;// height of current page in points
    protected float $currentWidth;       // width of current page in user unit
    protected float $currentHeight;      // height of current page in user unit
    protected float $leftMargin;         // left margin
    protected float $topMargin;          // top margin
    protected float $rightMargin;        // right margin
    protected float $pageBreakMargin;    // page break margin
    protected float $cellMargin;         // cell margin
    protected float $xPosition;          // current x position in user unit
    protected float $yPosition;          // current y position in user unit
    protected float $lastHeight;         // height of last printed cell
    protected float $lineWidth;          // line width in user unit
    protected string $fontPath;          // path containing fonts
    protected array $coreFonts;          // array of core font names
    protected array $fonts;              // array of used fonts
    protected array $fontFiles;          // array of font files
    protected array $encodings;          // array of encodings
    protected array $characterMaps;      // array of ToUnicode CMaps
    protected string $fontFamily;        // current font family
    protected string $fontStyle;         // current font style
    protected bool $underline;           // underlining flag
    protected array $currentFont;        // current font info
    protected float $fontSizePoints;     // current font size in points
    protected float $fontSize;           // current font size in user unit
    protected string $drawColor;         // commands for drawing color
    protected string $fillColor;         // commands for filling color
    protected string $textColor;         // commands for text color
    protected bool $colorFlag;           // indicates whether fill and text colors are different
    protected bool $withAlpha;           // indicates whether alpha channel is used
    protected float $wordSpacing;        // word spacing
    protected array $images;             // array of used images
    protected array $pageLinks;          // array of links in pages
    protected array $links;              // array of internal links
    protected bool $autoPageBreak;       // automatic page breaking
    protected float $pageBreakTrigger;   // threshold used to trigger page breaks
    protected bool $inHeader;            // flag set when processing header
    protected bool $inFooter;            // flag set when processing footer
    protected string $aliasNumberPages;  // alias for total number of pages
    protected string|float $zoomMode;    // zoom display mode
    protected string $layoutMode;        // layout display mode
    protected array $metadata;           // document properties
    protected string $PDFVersion;        // PDF version number

    /*******************************************************************************
    *                               Public methods                                 *
    *******************************************************************************/

    public function __construct(string $orientation='P', string $unit='mm', string|array $size='A4') {
        // Some checks
        $this->_dochecks();
        // Initialization of properties
        $this->state = 0;
        $this->currentPage = 0;
        $this->currentObject = 2;
        $this->buffer = '';
        $this->pages = array();
        $this->pageInfo = array();
        $this->fonts = array();
        $this->fontFiles = array();
        $this->encodings = array();
        $this->characterMaps = array();
        $this->images = array();
        $this->links = array();
        $this->inHeader = false;
        $this->inFooter = false;
        $this->lastHeight = 0;
        $this->fontFamily = '';
        $this->fontStyle = '';
        $this->fontSizePoints = 12;
        $this->underline = false;
        $this->drawColor = '0 G';
        $this->fillColor = '0 g';
        $this->textColor = '0 g';
        $this->colorFlag = false;
        $this->withAlpha = false;
        $this->wordSpacing = 0;
        // Font path
        if(defined('PHPDF_FONTPATH')) {
            $this->fontPath = PHPDF_FONTPATH;
            if(substr($this->fontPath,-1)!='/' && substr($this->fontPath,-1)!='\\')
                $this->fontPath .= '/';
        }
        elseif(is_dir(dirname(__FILE__).'/font'))
            $this->fontPath = dirname(__FILE__).'/font/';
        else
            $this->fontPath = '';
        // Core fonts
        $this->coreFonts = array('courier', 'helvetica', 'times', 'symbol', 'zapfdingbats');
        // Scale factor
        if($unit=='pt')
            $this->scale = 1;
        elseif($unit=='mm')
            $this->scale = 72/25.4;
        elseif($unit=='cm')
            $this->scale = 72/2.54;
        elseif($unit=='in')
            $this->scale = 72;
        else
            $this->Error('Incorrect unit: '.$unit);
        // Page sizes
        $this->standardPageSizes = array('a3'=>array(841.89,1190.55), 'a4'=>array(595.28,841.89), 'a5'=>array(420.94,595.28),
            'letter'=>array(612,792), 'legal'=>array(612,1008));
        $size = $this->_getpagesize($size);
        $this->defaultPageSize = $size;
        $this->currentPageSize = $size;
        // Page orientation
        $orientation = strtolower($orientation);
        if($orientation=='p' || $orientation=='portrait') {
            $this->defaultOrientation = 'P';
            $this->currentWidth = $size[0];
            $this->currentHeight = $size[1];
        }
        elseif($orientation=='l' || $orientation=='landscape') {
            $this->defaultOrientation = 'L';
            $this->currentWidth = $size[1];
            $this->currentHeight = $size[0];
        }
        else
            $this->Error('Incorrect orientation: '.$orientation);
        $this->currentOrientation = $this->defaultOrientation;
        $this->currentWidthPoints = $this->currentWidth*$this->scale;
        $this->currentHeightPoints = $this->currentHeight*$this->scale;
        // Page rotation
        $this->currentRotation = 0;
        // Page margins (1 cm)
        $margin = 28.35/$this->scale;
        $this->SetMargins($margin,$margin);
        // Interior cell margin (1 mm)
        $this->cellMargin = $margin/10;
        // Line width (0.2 mm)
        $this->lineWidth = .567/$this->scale;
        // Automatic page break
        $this->SetAutoPageBreak(true,2*$margin);
        // Default display mode
        $this->SetDisplayMode('default');
        // Enable compression
        $this->SetCompression(true);
        // Set default PDF version number
        $this->PDFVersion = '1.3';
    }

    public function SetMargins(float $left, float $top, ?float $right=null): void {
        // Set left, top and right margins
        $this->leftMargin = $left;
        $this->topMargin = $top;
        if($right===null)
            $right = $left;
        $this->rightMargin = $right;
    }

    public function SetLeftMargin(float $margin): void {
        // Set left margin
        $this->leftMargin = $margin;
        if($this->currentPage>0 && $this->xPosition<$margin)
            $this->xPosition = $margin;
    }

    public function SetTopMargin(float $margin): void {
        // Set top margin
        $this->topMargin = $margin;
    }

    public function SetRightMargin(float $margin): void {
        // Set right margin
        $this->rightMargin = $margin;
    }

    public function SetAutoPageBreak(bool $auto, float $margin=0): void {
        // Set auto page break mode and triggering margin
        $this->autoPageBreak = $auto;
        $this->pageBreakMargin = $margin;
        $this->pageBreakTrigger = $this->currentHeight-$margin;
    }

    public function SetDisplayMode(string|float $zoom, string $layout='default'): void {
        // Set display mode in viewer
        if($zoom=='fullpage' || $zoom=='fullwidth' || $zoom=='real' || $zoom=='default' || !is_string($zoom))
            $this->zoomMode = $zoom;
        else
            $this->Error('Incorrect zoom display mode: '.$zoom);
        if($layout=='single' || $layout=='continuous' || $layout=='two' || $layout=='default')
            $this->layoutMode = $layout;
        else
            $this->Error('Incorrect layout display mode: '.$layout);
    }

    public function SetCompression(bool $compress): void {
        // Set page compression
        if(function_exists('gzcompress'))
            $this->compress = $compress;
        else
            $this->compress = false;
    }

    public function SetTitle(string $title, bool $isUTF8=false): void {
        // Title of document
        $this->metadata['Title'] = $isUTF8 ? $title : utf8_encode($title);
    }

    public function SetAuthor(string $author, bool $isUTF8=false): void {
        // Author of document
        $this->metadata['Author'] = $isUTF8 ? $author : utf8_encode($author);
    }

    public function SetSubject(string $subject, bool $isUTF8=false): void {
        // Subject of document
        $this->metadata['Subject'] = $isUTF8 ? $subject : utf8_encode($subject);
    }

    public function SetKeywords(string $keywords, bool $isUTF8=false): void {
        // Keywords of document
        $this->metadata['Keywords'] = $isUTF8 ? $keywords : utf8_encode($keywords);
    }

    public function SetCreator(string $creator, bool $isUTF8=false): void {
        // Creator of document
        $this->metadata['Creator'] = $isUTF8 ? $creator : utf8_encode($creator);
    }

    public function AliasNbPages(string $alias='{nb}'): void {
        // Define an alias for total number of pages
        $this->aliasNumberPages = $alias;
    }

    public function Error(string $msg): void {
        // Fatal error
        throw new \Exception('PHPDF error: '.$msg);
    }

    public function Close(): void {
        // Terminate document
        if($this->state==3)
            return;
        if($this->currentPage==0)
            $this->AddPage();
        // Page footer
        $this->inFooter = true;
        $this->Footer();
        $this->inFooter = false;
        // Close page
        $this->_endpage();
        // Close document
        $this->_enddoc();
    }

    public function AddPage(string $orientation='', string|array $size='', int $rotation=0): void {
        // Start a new page
        if($this->state==3)
            $this->Error('The document is closed');
        $family = $this->fontFamily;
        $style = $this->fontStyle.($this->underline ? 'U' : '');
        $fontsize = $this->fontSizePoints;
        $lw = $this->lineWidth;
        $dc = $this->drawColor;
        $fc = $this->fillColor;
        $tc = $this->textColor;
        $cf = $this->colorFlag;
        if($this->currentPage>0) {
            // Page footer
            $this->inFooter = true;
            $this->Footer();
            $this->inFooter = false;
            // Close page
            $this->_endpage();
        }
        // Start new page
        $this->_beginpage($orientation,$size,$rotation);
        // Set line cap style to square
        $this->_out('2 J');
        // Set line width
        $this->lineWidth = $lw;
        $this->_out(sprintf('%.2F w',$lw*$this->scale));
        // Set font
        if($family)
            $this->SetFont($family,$style,$fontsize);
        // Set colors
        $this->drawColor = $dc;
        if($dc!='0 G')
            $this->_out($dc);
        $this->fillColor = $fc;
        if($fc!='0 g')
            $this->_out($fc);
        $this->textColor = $tc;
        $this->colorFlag = $cf;
        // Page header
        $this->inHeader = true;
        $this->Header();
        $this->inHeader = false;
        // Restore line width
        if($this->lineWidth!=$lw) {
            $this->lineWidth = $lw;
            $this->_out(sprintf('%.2F w',$lw*$this->scale));
        }
        // Restore font
        if($family)
            $this->SetFont($family,$style,$fontsize);
        // Restore colors
        if($this->drawColor!=$dc) {
            $this->drawColor = $dc;
            $this->_out($dc);
        }
        if($this->fillColor!=$fc) {
            $this->fillColor = $fc;
            $this->_out($fc);
        }
        $this->textColor = $tc;
        $this->colorFlag = $cf;
    }

    public function Header() {
        // To be implemented in your own inherited class
    }

    public function Footer() {
        // To be implemented in your own inherited class
    }

    public function PageNo(): int {
        // Get current page number
        return $this->currentPage;
    }

    public function SetDrawColor(int $r, ?int $g=null, ?int $b=null): void {
        // Set color for all stroking operations
        if(($r==0 && $g==0 && $b==0) || $g===null)
            $this->drawColor = sprintf('%.3F G',$r/255);
        else
            $this->drawColor = sprintf('%.3F %.3F %.3F RG',$r/255,$g/255,$b/255);
        if($this->currentPage>0)
            $this->_out($this->drawColor);
    }

    public function SetFillColor(int $r, ?int $g=null, ?int $b=null): void {
        // Set color for all filling operations
        if(($r==0 && $g==0 && $b==0) || $g===null)
            $this->fillColor = sprintf('%.3F g',$r/255);
        else
            $this->fillColor = sprintf('%.3F %.3F %.3F rg',$r/255,$g/255,$b/255);
        $this->colorFlag = ($this->fillColor!=$this->textColor);
        if($this->currentPage>0)
            $this->_out($this->fillColor);
    }

    public function SetTextColor(int $r, ?int $g=null, ?int $b=null): void {
        // Set color for text
        if(($r==0 && $g==0 && $b==0) || $g===null)
            $this->textColor = sprintf('%.3F g',$r/255);
        else
            $this->textColor = sprintf('%.3F %.3F %.3F rg',$r/255,$g/255,$b/255);
        $this->colorFlag = ($this->fillColor!=$this->textColor);
    }

    public function GetStringWidth(string $s): float {
        // Get width of a string in the current font
        $s = (string)$s;
        $cw = &$this->currentFont['cw'];
        $w = 0;
        $l = strlen($s);
        for($i=0;$i<$l;$i++)
            $w += $cw[$s[$i]];
        return $w*$this->fontSize/1000;
    }

    public function SetLineWidth(float $width): void {
        // Set line width
        $this->lineWidth = $width;
        if($this->currentPage>0)
            $this->_out(sprintf('%.2F w',$width*$this->scale));
    }

    public function Line(float $x1, float $y1, float $x2, float $y2): void {
        // Draw a line
        $this->_out(sprintf('%.2F %.2F m %.2F %.2F l S',$x1*$this->scale,($this->currentHeight-$y1)*$this->scale,$x2*$this->scale,($this->currentHeight-$y2)*$this->scale));
    }

    public function Rect(float $x, float $y, float $w, float $h, string $style=''): void {
        // Draw a rectangle
        if($style=='F')
            $op = 'f';
        elseif($style=='FD' || $style=='DF')
            $op = 'B';
        else
            $op = 'S';
        $this->_out(sprintf('%.2F %.2F %.2F %.2F re %s',$x*$this->scale,($this->currentHeight-$y)*$this->scale,$w*$this->scale,-$h*$this->scale,$op));
    }

    public function AddFont(string $family, string $style='', string $file=''): void {
        // Add a TrueType, OpenType or Type1 font
        $family = strtolower($family);
        if($file=='')
            $file = str_replace(' ','',$family).strtolower($style).'.php';
        $style = strtoupper($style);
        if($style=='IB')
            $style = 'BI';
        $fontkey = $family.$style;
        if(isset($this->fonts[$fontkey]))
            return;
        $info = $this->_loadfont($file);
        $info['i'] = count($this->fonts)+1;
        if(!empty($info['file'])) {
            // Embedded font
            if($info['type']=='TrueType')
                $this->fontFiles[$info['file']] = array('length1'=>$info['originalsize']);
            else
                $this->fontFiles[$info['file']] = array('length1'=>$info['size1'], 'length2'=>$info['size2']);
        }
        $this->fonts[$fontkey] = $info;
    }

    public function SetFont(string $family, string $style='', float $size=0): void {
        // Select a font; size given in points
        if($family=='')
            $family = $this->fontFamily;
        else
            $family = strtolower($family);
        $style = strtoupper($style);
        if(strpos($style,'U')!==false) {
            $this->underline = true;
            $style = str_replace('U','',$style);
        }
        else
            $this->underline = false;
        if($style=='IB')
            $style = 'BI';
        if($size==0)
            $size = $this->fontSizePoints;
        // Test if font is already selected
        if($this->fontFamily==$family && $this->fontStyle==$style && $this->fontSizePoints==$size)
            return;
        // Test if font is already loaded
        $fontkey = $family.$style;
        if(!isset($this->fonts[$fontkey])) {
            // Test if one of the core fonts
            if($family=='arial')
                $family = 'helvetica';
            if(in_array($family,$this->coreFonts)) {
                if($family=='symbol' || $family=='zapfdingbats')
                    $style = '';
                $fontkey = $family.$style;
                if(!isset($this->fonts[$fontkey]))
                    $this->AddFont($family,$style);
            }
            else
                $this->Error('Undefined font: '.$family.' '.$style);
        }
        // Select it
        $this->fontFamily = $family;
        $this->fontStyle = $style;
        $this->fontSizePoints = $size;
        $this->fontSize = $size/$this->scale;
        $this->currentFont = &$this->fonts[$fontkey];
        if($this->currentPage>0)
            $this->_out(sprintf('BT /F%d %.2F Tf ET',$this->currentFont['i'],$this->fontSizePoints));
    }

    public function SetFontSize(float $size): void {
        // Set font size in points
        if($this->fontSizePoints==$size)
            return;
        $this->fontSizePoints = $size;
        $this->fontSize = $size/$this->scale;
        if($this->currentPage>0)
            $this->_out(sprintf('BT /F%d %.2F Tf ET',$this->currentFont['i'],$this->fontSizePoints));
    }

    public function AddLink(): int {
        // Create a new internal link
        $n = count($this->links)+1;
        $this->links[$n] = array(0, 0);
        return $n;
    }

    public function SetLink(int $link, float $y=0, int $page=-1): void {
        // Set destination of internal link
        if($y==-1)
            $y = $this->yPosition;
        if($page==-1)
            $page = $this->currentPage;
        $this->links[$link] = array($page, $y);
    }

    public function Link(float $x, float $y, float $w, float $h, string|int $link): void {
        // Put a link on the page
        $this->pageLinks[$this->currentPage][] = array($x*$this->scale, $this->currentHeightPoints-$y*$this->scale, $w*$this->scale, $h*$this->scale, $link);
    }

    public function Text(float $x, float $y, string $txt): void {
        // Output a string
        if(!isset($this->currentFont))
            $this->Error('No font has been set');
        $s = sprintf('BT %.2F %.2F Td (%s) Tj ET',$x*$this->scale,($this->currentHeight-$y)*$this->scale,$this->_escape($txt));
        if($this->underline && $txt!='')
            $s .= ' '.$this->_dounderline($x,$y,$txt);
        if($this->colorFlag)
            $s = 'q '.$this->textColor.' '.$s.' Q';
        $this->_out($s);
    }

    public function AcceptPageBreak(): bool {
        // Accept automatic page break or not
        return $this->autoPageBreak;
    }

    public function Cell(float $w, float $h=0, string $txt='', int|string $border=0, int $ln=0, string $align='', bool $fill=false, string|int $link=''): void {
        // Output a cell
        $k = $this->scale;
        if($this->yPosition+$h>$this->pageBreakTrigger && !$this->inHeader && !$this->inFooter && $this->AcceptPageBreak()) {
            // Automatic page break
            $x = $this->xPosition;
            $wordSpacing = $this->wordSpacing;
            if($wordSpacing>0) {
                $this->wordSpacing = 0;
                $this->_out('0 Tw');
            }
            $this->AddPage($this->currentOrientation,$this->currentPageSize,$this->currentRotation);
            $this->xPosition = $x;
            if($wordSpacing>0) {
                $this->wordSpacing = $wordSpacing;
                $this->_out(sprintf('%.3F Tw',$wordSpacing*$k));
            }
        }
        if($w==0)
            $w = $this->currentWidth-$this->rightMargin-$this->xPosition;
        $s = '';
        if($fill || $border==1) {
            if($fill)
                $op = ($border==1) ? 'B' : 'f';
            else
                $op = 'S';
            $s = sprintf('%.2F %.2F %.2F %.2F re %s ',$this->xPosition*$k,($this->currentHeight-$this->yPosition)*$k,$w*$k,-$h*$k,$op);
        }
        if(is_string($border)) {
            $x = $this->xPosition;
            $y = $this->yPosition;
            if(strpos($border,'L')!==false)
                $s .= sprintf('%.2F %.2F m %.2F %.2F l S ',$x*$k,($this->currentHeight-$y)*$k,$x*$k,($this->currentHeight-($y+$h))*$k);
            if(strpos($border,'T')!==false)
                $s .= sprintf('%.2F %.2F m %.2F %.2F l S ',$x*$k,($this->currentHeight-$y)*$k,($x+$w)*$k,($this->currentHeight-$y)*$k);
            if(strpos($border,'R')!==false)
                $s .= sprintf('%.2F %.2F m %.2F %.2F l S ',($x+$w)*$k,($this->currentHeight-$y)*$k,($x+$w)*$k,($this->currentHeight-($y+$h))*$k);
            if(strpos($border,'B')!==false)
                $s .= sprintf('%.2F %.2F m %.2F %.2F l S ',$x*$k,($this->currentHeight-($y+$h))*$k,($x+$w)*$k,($this->currentHeight-($y+$h))*$k);
        }
        if($txt!=='') {
            if(!isset($this->currentFont))
                $this->Error('No font has been set');
            if($align=='R')
                $dx = $w-$this->cellMargin-$this->GetStringWidth($txt);
            elseif($align=='C')
                $dx = ($w-$this->GetStringWidth($txt))/2;
            else
                $dx = $this->cellMargin;
            if($this->colorFlag)
                $s .= 'q '.$this->textColor.' ';
            $s .= sprintf('BT %.2F %.2F Td (%s) Tj ET',($this->xPosition+$dx)*$k,($this->currentHeight-($this->yPosition+.5*$h+.3*$this->fontSize))*$k,$this->_escape($txt));
            if($this->underline)
                $s .= ' '.$this->_dounderline($this->xPosition+$dx,$this->yPosition+.5*$h+.3*$this->fontSize,$txt);
            if($this->colorFlag)
                $s .= ' Q';
            if($link)
                $this->Link($this->xPosition+$dx,$this->yPosition+.5*$h-.5*$this->fontSize,$this->GetStringWidth($txt),$this->fontSize,$link);
        }
        if($s)
            $this->_out($s);
        $this->lastHeight = $h;
        if($ln>0) {
            // Go to next line
            $this->yPosition += $h;
            if($ln==1)
                $this->xPosition = $this->leftMargin;
        }
        else
            $this->xPosition += $w;
    }

    public function MultiCell(float $w, float $h, string $txt, int|string $border=0, string $align='J', bool $fill=false): void {
        // Output text with automatic or explicit line breaks
        if(!isset($this->currentFont))
            $this->Error('No font has been set');
        $cw = &$this->currentFont['cw'];
        if($w==0)
            $w = $this->currentWidth-$this->rightMargin-$this->xPosition;
        $wmax = ($w-2*$this->cellMargin)*1000/$this->fontSize;
        $s = str_replace("\r",'',$txt);
        $nb = strlen($s);
        if($nb>0 && $s[$nb-1]=="\n")
            $nb--;
        $b = 0;
        if($border) {
            if($border==1) {
                $border = 'LTRB';
                $b = 'LRT';
                $b2 = 'LR';
            }
            else {
                $b2 = '';
                if(strpos($border,'L')!==false)
                    $b2 .= 'L';
                if(strpos($border,'R')!==false)
                    $b2 .= 'R';
                $b = (strpos($border,'T')!==false) ? $b2.'T' : $b2;
            }
        }
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $ns = 0;
        $nl = 1;
        while($i<$nb) {
            // Get next character
            $c = $s[$i];
            if($c=="\n") {
                // Explicit line break
                if($this->wordSpacing>0) {
                    $this->wordSpacing = 0;
                    $this->_out('0 Tw');
                }
                $this->Cell($w,$h,substr($s,$j,$i-$j),$b,2,$align,$fill);
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $ns = 0;
                $nl++;
                if($border && $nl==2)
                    $b = $b2;
                continue;
            }
            if($c==' ') {
                $sep = $i;
                $ls = $l;
                $ns++;
            }
            $l += $cw[$c];
            if($l>$wmax) {
                // Automatic line break
                if($sep==-1) {
                    if($i==$j)
                        $i++;
                    if($this->wordSpacing>0)
                    {
                        $this->wordSpacing = 0;
                        $this->_out('0 Tw');
                    }
                    $this->Cell($w,$h,substr($s,$j,$i-$j),$b,2,$align,$fill);
                }
                else
                {
                    if($align=='J')
                    {
                        $this->wordSpacing = ($ns>1) ? ($wmax-$ls)/1000*$this->fontSize/($ns-1) : 0;
                        $this->_out(sprintf('%.3F Tw',$this->wordSpacing*$this->scale));
                    }
                    $this->Cell($w,$h,substr($s,$j,$sep-$j),$b,2,$align,$fill);
                    $i = $sep+1;
                }
                $sep = -1;
                $j = $i;
                $l = 0;
                $ns = 0;
                $nl++;
                if($border && $nl==2)
                    $b = $b2;
            }
            else
                $i++;
        }
        // Last chunk
        if($this->wordSpacing>0) {
            $this->wordSpacing = 0;
            $this->_out('0 Tw');
        }
        if($border && strpos($border,'B')!==false)
            $b .= 'B';
        $this->Cell($w,$h,substr($s,$j,$i-$j),$b,2,$align,$fill);
        $this->xPosition = $this->leftMargin;
    }

    public function Write(float $h, string $txt, string|int $link=''): void {
        // Output text in flowing mode
        if(!isset($this->currentFont))
            $this->Error('No font has been set');
        $cw = &$this->currentFont['cw'];
        $w = $this->currentWidth-$this->rightMargin-$this->xPosition;
        $wmax = ($w-2*$this->cellMargin)*1000/$this->fontSize;
        $s = str_replace("\r",'',$txt);
        $nb = strlen($s);
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $nl = 1;
        while($i<$nb) {
            // Get next character
            $c = $s[$i];
            if($c=="\n") {
                // Explicit line break
                $this->Cell($w,$h,substr($s,$j,$i-$j),0,2,'',false,$link);
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                if($nl==1) {
                    $this->xPosition = $this->leftMargin;
                    $w = $this->currentWidth-$this->rightMargin-$this->xPosition;
                    $wmax = ($w-2*$this->cellMargin)*1000/$this->fontSize;
                }
                $nl++;
                continue;
            }
            if($c==' ')
                $sep = $i;
            $l += $cw[$c];
            if($l>$wmax) {
                // Automatic line break
                if($sep==-1) {
                    if($this->xPosition>$this->leftMargin)
                    {
                        // Move to next line
                        $this->xPosition = $this->leftMargin;
                        $this->yPosition += $h;
                        $w = $this->currentWidth-$this->rightMargin-$this->xPosition;
                        $wmax = ($w-2*$this->cellMargin)*1000/$this->fontSize;
                        $i++;
                        $nl++;
                        continue;
                    }
                    if($i==$j)
                        $i++;
                    $this->Cell($w,$h,substr($s,$j,$i-$j),0,2,'',false,$link);
                }
                else
                {
                    $this->Cell($w,$h,substr($s,$j,$sep-$j),0,2,'',false,$link);
                    $i = $sep+1;
                }
                $sep = -1;
                $j = $i;
                $l = 0;
                if($nl==1) {
                    $this->xPosition = $this->leftMargin;
                    $w = $this->currentWidth-$this->rightMargin-$this->xPosition;
                    $wmax = ($w-2*$this->cellMargin)*1000/$this->fontSize;
                }
                $nl++;
            }
            else
                $i++;
        }
        // Last chunk
        if($i!=$j)
            $this->Cell($l/1000*$this->fontSize,$h,substr($s,$j),0,0,'',false,$link);
    }

    public function Ln(?float $h=null): void {
        // Line feed; default value is the last cell height
        $this->xPosition = $this->leftMargin;
        if($h===null)
            $this->yPosition += $this->lastHeight;
        else
            $this->yPosition += $h;
    }

    public function Image(string $file, ?float $x=null, ?float $y=null, float $w=0, float $h=0, string $type='', string|int $link=''): void {
        // Put an image on the page
        if($file=='')
            $this->Error('Image file name is empty');
        if(!isset($this->images[$file])) {
            // First use of this image, get info
            if($type=='') {
                $pos = strrpos($file,'.');
                if(!$pos)
                    $this->Error('Image file has no extension and no type was specified: '.$file);
                $type = substr($file,$pos+1);
            }
            $type = strtolower($type);
            if($type=='jpeg')
                $type = 'jpg';
            $mtd = '_parse'.$type;
            if(!method_exists($this,$mtd))
                $this->Error('Unsupported image type: '.$type);
            $info = $this->$mtd($file);
            $info['i'] = count($this->images)+1;
            $this->images[$file] = $info;
        }
        else
            $info = $this->images[$file];

        // Automatic width and height calculation if needed
        if($w==0 && $h==0) {
            // Put image at 96 dpi
            $w = -96;
            $h = -96;
        }
        if($w<0)
            $w = -$info['w']*72/$w/$this->scale;
        if($h<0)
            $h = -$info['h']*72/$h/$this->scale;
        if($w==0)
            $w = $h*$info['w']/$info['h'];
        if($h==0)
            $h = $w*$info['h']/$info['w'];

        // Flowing mode
        if($y===null) {
            if($this->yPosition+$h>$this->pageBreakTrigger && !$this->inHeader && !$this->inFooter && $this->AcceptPageBreak()) {
                // Automatic page break
                $x2 = $this->xPosition;
                $this->AddPage($this->currentOrientation,$this->currentPageSize,$this->currentRotation);
                $this->xPosition = $x2;
            }
            $y = $this->yPosition;
            $this->yPosition += $h;
        }

        if($x===null)
            $x = $this->xPosition;
        $this->_out(sprintf('q %.2F 0 0 %.2F %.2F %.2F cm /I%d Do Q',$w*$this->scale,$h*$this->scale,$x*$this->scale,($this->currentHeight-($y+$h))*$this->scale,$info['i']));
        if($link)
            $this->Link($x,$y,$w,$h,$link);
    }

    public function GetPageWidth(): float {
        // Get current page width
        return $this->currentWidth;
    }

    public function GetPageHeight(): float {
        // Get current page height
        return $this->currentHeight;
    }

    public function GetX(): float {
        // Get x position
        return $this->xPosition;
    }

    public function SetX(float $x): void {
        // Set x position
        if($x>=0)
            $this->xPosition = $x;
        else
            $this->xPosition = $this->currentWidth+$x;
    }

    public function GetY(): float {
        // Get y position
        return $this->yPosition;
    }

    public function SetY(float $y, bool $resetX=true): void {
        // Set y position and optionally reset x
        if($y>=0)
            $this->yPosition = $y;
        else
            $this->yPosition = $this->currentHeight+$y;
        if($resetX)
            $this->xPosition = $this->leftMargin;
    }

    public function SetXY(float $x, float $y) {
        // Set x and y positions
        $this->SetX($x);
        $this->SetY($y,false);
    }

    public function Output(string $dest='', string $name='', bool $isUTF8=false): string {
        // Output PDF to some destination
        $this->Close();
        if(strlen($name)==1 && strlen($dest)!=1) {
            // Fix parameter order
            $tmp = $dest;
            $dest = $name;
            $name = $tmp;
        }
        if($dest=='')
            $dest = 'I';
        if($name=='')
            $name = 'doc.pdf';
        switch(strtoupper($dest)) {
            case 'I':
                // Send to standard output
                $this->_checkoutput();
                if(PHP_SAPI!='cli') {
                    // We send to a browser
                    header('Content-Type: application/pdf');
                    header('Content-Disposition: inline; '.$this->_httpencode('filename',$name,$isUTF8));
                    header('Cache-Control: private, max-age=0, must-revalidate');
                    header('Pragma: public');
                }
                echo $this->buffer;
                break;
            case 'D':
                // Download file
                $this->_checkoutput();
                header('Content-Type: application/x-download');
                header('Content-Disposition: attachment; '.$this->_httpencode('filename',$name,$isUTF8));
                header('Cache-Control: private, max-age=0, must-revalidate');
                header('Pragma: public');
                echo $this->buffer;
                break;
            case 'F':
                // Save to local file
                if(!file_put_contents($name,$this->buffer))
                    $this->Error('Unable to create output file: '.$name);
                break;
            case 'S':
                // Return as a string
                return $this->buffer;
            default:
                $this->Error('Incorrect output destination: '.$dest);
        }
        return '';
    }

    /*******************************************************************************
    *                              Protected methods                               *
    *******************************************************************************/

    protected function _dochecks() {
        // Check mbstring overloading
        if(ini_get('mbstring.func_overload') & 2)
            $this->Error('mbstring overloading must be disabled');
    }

    protected function _checkoutput() {
        if(PHP_SAPI!='cli') {
            if(headers_sent($file,$line))
                $this->Error("Some data has already been output, can't send PDF file (output started at $file:$line)");
        }
        if(ob_get_length()) {
            // The output buffer is not empty
            if(preg_match('/^(\xEF\xBB\xBF)?\s*$/',ob_get_contents())) {
                // It contains only a UTF-8 BOM and/or whitespace, let's clean it
                ob_clean();
            }
            else
                $this->Error("Some data has already been output, can't send PDF file");
        }
    }

    protected function _getpagesize($size) {
        if(is_string($size)) {
            $size = strtolower($size);
            if(!isset($this->standardPageSizes[$size]))
                $this->Error('Unknown page size: '.$size);
            $a = $this->standardPageSizes[$size];
            return array($a[0]/$this->scale, $a[1]/$this->scale);
        }
        else
        {
            if($size[0]>$size[1])
                return array($size[1], $size[0]);
            else
                return $size;
        }
    }

    protected function _beginpage($orientation, $size, $rotation) {
        $this->currentPage++;
        $this->pages[$this->currentPage] = '';
        $this->state = 2;
        $this->xPosition = $this->leftMargin;
        $this->yPosition = $this->topMargin;
        $this->fontFamily = '';
        // Check page size and orientation
        if($orientation=='')
            $orientation = $this->defaultOrientation;
        else
            $orientation = strtoupper($orientation[0]);
        if($size=='')
            $size = $this->defaultPageSize;
        else
            $size = $this->_getpagesize($size);
        if($orientation!=$this->currentOrientation || $size[0]!=$this->currentPageSize[0] || $size[1]!=$this->currentPageSize[1]) {
            // New size or orientation
            if($orientation=='P') {
                $this->currentWidth = $size[0];
                $this->currentHeight = $size[1];
            }
            else {
                $this->currentWidth = $size[1];
                $this->currentHeight = $size[0];
            }
            $this->currentWidthPoints = $this->currentWidth*$this->scale;
            $this->currentHeightPoints = $this->currentHeight*$this->scale;
            $this->pageBreakTrigger = $this->currentHeight-$this->pageBreakMargin;
            $this->currentOrientation = $orientation;
            $this->currentPageSize = $size;
        }
        if($orientation!=$this->defaultOrientation || $size[0]!=$this->defaultPageSize[0] || $size[1]!=$this->defaultPageSize[1])
            $this->pageInfo[$this->currentPage]['size'] = array($this->currentWidthPoints, $this->currentHeightPoints);
        if($rotation!=0) {
            if($rotation%90!=0)
                $this->Error('Incorrect rotation value: '.$rotation);
            $this->currentRotation = $rotation;
            $this->pageInfo[$this->currentPage]['rotation'] = $rotation;
        }
    }

    protected function _endpage() {
        $this->state = 1;
    }

    protected function _loadfont($font) {
        // Load a font definition file from the font directory
        if(strpos($font,'/')!==false || strpos($font,"\\")!==false)
            $this->Error('Incorrect font definition file name: '.$font);
        include($this->fontPath.$font);
        if(!isset($name))
            $this->Error('Could not include font definition file');
        if(isset($enc))
            $enc = strtolower($enc);
        if(!isset($subsetted))
            $subsetted = false;
        return get_defined_vars();
    }

    protected function _isascii($s) {
        // Test if string is ASCII
        $nb = strlen($s);
        for($i=0;$i<$nb;$i++) {
            if(ord($s[$i])>127)
                return false;
        }
        return true;
    }

    protected function _httpencode($param, $value, $isUTF8) {
        // Encode HTTP header field parameter
        if($this->_isascii($value))
            return $param.'="'.$value.'"';
        if(!$isUTF8)
            $value = utf8_encode($value);
        if(strpos($_SERVER['HTTP_USER_AGENT'],'MSIE')!==false)
            return $param.'="'.rawurlencode($value).'"';
        else
            return $param."*=UTF-8''".rawurlencode($value);
    }

    protected function _UTF8toUTF16($s) {
        // Convert UTF-8 to UTF-16BE with BOM
        $res = "\xFE\xFF";
        $nb = strlen($s);
        $i = 0;
        while($i<$nb) {
            $c1 = ord($s[$i++]);
            if($c1>=224) {
                // 3-byte character
                $c2 = ord($s[$i++]);
                $c3 = ord($s[$i++]);
                $res .= chr((($c1 & 0x0F)<<4) + (($c2 & 0x3C)>>2));
                $res .= chr((($c2 & 0x03)<<6) + ($c3 & 0x3F));
            }
            elseif($c1>=192) {
                // 2-byte character
                $c2 = ord($s[$i++]);
                $res .= chr(($c1 & 0x1C)>>2);
                $res .= chr((($c1 & 0x03)<<6) + ($c2 & 0x3F));
            }
            else {
                // Single-byte character
                $res .= "\0".chr($c1);
            }
        }
        return $res;
    }

    protected function _escape($s) {
        // Escape special characters
        if(strpos($s,'(')!==false || strpos($s,')')!==false || strpos($s,'\\')!==false || strpos($s,"\r")!==false)
            return str_replace(array('\\','(',')',"\r"), array('\\\\','\\(','\\)','\\r'), $s);
        else
            return $s;
    }

    protected function _textstring($s) {
        // Format a text string
        if(!$this->_isascii($s))
            $s = $this->_UTF8toUTF16($s);
        return '('.$this->_escape($s).')';
    }

    protected function _dounderline($x, $y, $txt) {
        // Underline text
        $up = $this->currentFont['up'];
        $ut = $this->currentFont['ut'];
        $w = $this->GetStringWidth($txt)+$this->wordSpacing*substr_count($txt,' ');
        return sprintf('%.2F %.2F %.2F %.2F re f',$x*$this->scale,($this->currentHeight-($y-$up/1000*$this->fontSize))*$this->scale,$w*$this->scale,-$ut/1000*$this->fontSizePoints);
    }

    protected function _parsejpg($file) {
        // Extract info from a JPEG file
        $a = getimagesize($file);
        if(!$a)
            $this->Error('Missing or incorrect image file: '.$file);
        if($a[2]!=2)
            $this->Error('Not a JPEG file: '.$file);
        if(!isset($a['channels']) || $a['channels']==3)
            $colspace = 'DeviceRGB';
        elseif($a['channels']==4)
            $colspace = 'DeviceCMYK';
        else
            $colspace = 'DeviceGray';
        $bpc = isset($a['bits']) ? $a['bits'] : 8;
        $data = file_get_contents($file);
        return array('w'=>$a[0], 'h'=>$a[1], 'cs'=>$colspace, 'bpc'=>$bpc, 'f'=>'DCTDecode', 'data'=>$data);
    }

    protected function _parsepng($file) {
        // Extract info from a PNG file
        $f = fopen($file,'rb');
        if(!$f)
            $this->Error('Can\'t open image file: '.$file);
        $info = $this->_parsepngstream($f,$file);
        fclose($f);
        return $info;
    }

    protected function _parsepngstream($f, $file) {
        // Check signature
        if($this->_readstream($f,8)!=chr(137).'PNG'.chr(13).chr(10).chr(26).chr(10))
            $this->Error('Not a PNG file: '.$file);

        // Read header chunk
        $this->_readstream($f,4);
        if($this->_readstream($f,4)!='IHDR')
            $this->Error('Incorrect PNG file: '.$file);
        $w = $this->_readint($f);
        $h = $this->_readint($f);
        $bpc = ord($this->_readstream($f,1));
        if($bpc>8)
            $this->Error('16-bit depth not supported: '.$file);
        $ct = ord($this->_readstream($f,1));
        if($ct==0 || $ct==4)
            $colspace = 'DeviceGray';
        elseif($ct==2 || $ct==6)
            $colspace = 'DeviceRGB';
        elseif($ct==3)
            $colspace = 'Indexed';
        else
            $this->Error('Unknown color type: '.$file);
        if(ord($this->_readstream($f,1))!=0)
            $this->Error('Unknown compression method: '.$file);
        if(ord($this->_readstream($f,1))!=0)
            $this->Error('Unknown filter method: '.$file);
        if(ord($this->_readstream($f,1))!=0)
            $this->Error('Interlacing not supported: '.$file);
        $this->_readstream($f,4);
        $dp = '/Predictor 15 /Colors '.($colspace=='DeviceRGB' ? 3 : 1).' /BitsPerComponent '.$bpc.' /Columns '.$w;

        // Scan chunks looking for palette, transparency and image data
        $pal = '';
        $trns = '';
        $data = '';
        do
        {
            $n = $this->_readint($f);
            $type = $this->_readstream($f,4);
            if($type=='PLTE') {
                // Read palette
                $pal = $this->_readstream($f,$n);
                $this->_readstream($f,4);
            }
            elseif($type=='tRNS') {
                // Read transparency info
                $t = $this->_readstream($f,$n);
                if($ct==0)
                    $trns = array(ord(substr($t,1,1)));
                elseif($ct==2)
                    $trns = array(ord(substr($t,1,1)), ord(substr($t,3,1)), ord(substr($t,5,1)));
                else
                {
                    $pos = strpos($t,chr(0));
                    if($pos!==false)
                        $trns = array($pos);
                }
                $this->_readstream($f,4);
            }
            elseif($type=='IDAT') {
                // Read image data block
                $data .= $this->_readstream($f,$n);
                $this->_readstream($f,4);
            }
            elseif($type=='IEND')
                break;
            else
                $this->_readstream($f,$n+4);
        }
        while($n);

        if($colspace=='Indexed' && empty($pal))
            $this->Error('Missing palette in '.$file);
        $info = array('w'=>$w, 'h'=>$h, 'cs'=>$colspace, 'bpc'=>$bpc, 'f'=>'FlateDecode', 'dp'=>$dp, 'pal'=>$pal, 'trns'=>$trns);
        if($ct>=4) {
            // Extract alpha channel
            if(!function_exists('gzuncompress'))
                $this->Error('Zlib not available, can\'t handle alpha channel: '.$file);
            $data = gzuncompress($data);
            $color = '';
            $alpha = '';
            if($ct==4) {
                // Gray image
                $len = 2*$w;
                for($i=0;$i<$h;$i++) {
                    $pos = (1+$len)*$i;
                    $color .= $data[$pos];
                    $alpha .= $data[$pos];
                    $line = substr($data,$pos+1,$len);
                    $color .= preg_replace('/(.)./s','$1',$line);
                    $alpha .= preg_replace('/.(.)/s','$1',$line);
                }
            }
            else {
                // RGB image
                $len = 4*$w;
                for($i=0;$i<$h;$i++) {
                    $pos = (1+$len)*$i;
                    $color .= $data[$pos];
                    $alpha .= $data[$pos];
                    $line = substr($data,$pos+1,$len);
                    $color .= preg_replace('/(.{3})./s','$1',$line);
                    $alpha .= preg_replace('/.{3}(.)/s','$1',$line);
                }
            }
            unset($data);
            $data = gzcompress($color);
            $info['smask'] = gzcompress($alpha);
            $this->withAlpha = true;
            if($this->PDFVersion<'1.4')
                $this->PDFVersion = '1.4';
        }
        $info['data'] = $data;
        return $info;
    }

    protected function _readstream($f, $n) {
        // Read n bytes from stream
        $res = '';
        while($n>0 && !feof($f)) {
            $s = fread($f,$n);
            if($s===false)
                $this->Error('Error while reading stream');
            $n -= strlen($s);
            $res .= $s;
        }
        if($n>0)
            $this->Error('Unexpected end of stream');
        return $res;
    }

    protected function _readint($f) {
        // Read a 4-byte integer from stream
        $a = unpack('Ni',$this->_readstream($f,4));
        return $a['i'];
    }

    protected function _parsegif($file) {
        // Extract info from a GIF file (via PNG conversion)
        if(!function_exists('imagepng'))
            $this->Error('GD extension is required for GIF support');
        if(!function_exists('imagecreatefromgif'))
            $this->Error('GD has no GIF read support');
        $im = imagecreatefromgif($file);
        if(!$im)
            $this->Error('Missing or incorrect image file: '.$file);
        imageinterlace($im,0);
        ob_start();
        imagepng($im);
        $data = ob_get_clean();
        imagedestroy($im);
        $f = fopen('php://temp','rb+');
        if(!$f)
            $this->Error('Unable to create memory stream');
        fwrite($f,$data);
        rewind($f);
        $info = $this->_parsepngstream($f,$file);
        fclose($f);
        return $info;
    }

    protected function _out($s) {
        // Add a line to the document
        if($this->state==2)
            $this->pages[$this->currentPage] .= $s."\n";
        elseif($this->state==1)
            $this->_put($s);
        elseif($this->state==0)
            $this->Error('No page has been added yet');
        elseif($this->state==3)
            $this->Error('The document is closed');
    }

    protected function _put($s) {
        $this->buffer .= $s."\n";
    }

    protected function _getoffset() {
        return strlen($this->buffer);
    }

    protected function _newobj($n=null) {
        // Begin a new object
        if($n===null)
            $n = ++$this->currentObject;
        $this->offsets[$n] = $this->_getoffset();
        $this->_put($n.' 0 obj');
    }

    protected function _putstream($data) {
        $this->_put('stream');
        $this->_put($data);
        $this->_put('endstream');
    }

    protected function _putstreamobject($data) {
        if($this->compress) {
            $entries = '/Filter /FlateDecode ';
            $data = gzcompress($data);
        }
        else
            $entries = '';
        $entries .= '/Length '.strlen($data);
        $this->_newobj();
        $this->_put('<<'.$entries.'>>');
        $this->_putstream($data);
        $this->_put('endobj');
    }

    protected function _putpage($n) {
        $this->_newobj();
        $this->_put('<</Type /Page');
        $this->_put('/Parent 1 0 R');
        if(isset($this->pageInfo[$n]['size']))
            $this->_put(sprintf('/MediaBox [0 0 %.2F %.2F]',$this->pageInfo[$n]['size'][0],$this->pageInfo[$n]['size'][1]));
        if(isset($this->pageInfo[$n]['rotation']))
            $this->_put('/Rotate '.$this->pageInfo[$n]['rotation']);
        $this->_put('/Resources 2 0 R');
        if(isset($this->pageLinks[$n])) {
            // Links
            $annots = '/Annots [';
            foreach($this->pageLinks[$n] as $pl) {
                $rect = sprintf('%.2F %.2F %.2F %.2F',$pl[0],$pl[1],$pl[0]+$pl[2],$pl[1]-$pl[3]);
                $annots .= '<</Type /Annot /Subtype /Link /Rect ['.$rect.'] /Border [0 0 0] ';
                if(is_string($pl[4]))
                    $annots .= '/A <</S /URI /URI '.$this->_textstring($pl[4]).'>>>>';
                else
                {
                    $l = $this->links[$pl[4]];
                    if(isset($this->pageInfo[$l[0]]['size']))
                        $h = $this->pageInfo[$l[0]]['size'][1];
                    else
                        $h = ($this->defaultOrientation=='P') ? $this->defaultPageSize[1]*$this->scale : $this->defaultPageSize[0]*$this->scale;
                    $annots .= sprintf('/Dest [%d 0 R /XYZ 0 %.2F null]>>',$this->pageInfo[$l[0]]['n'],$h-$l[1]*$this->scale);
                }
            }
            $this->_put($annots.']');
        }
        if($this->withAlpha)
            $this->_put('/Group <</Type /Group /S /Transparency /CS /DeviceRGB>>');
        $this->_put('/Contents '.($this->currentObject+1).' 0 R>>');
        $this->_put('endobj');
        // Page content
        if(!empty($this->aliasNumberPages))
            $this->pages[$n] = str_replace($this->aliasNumberPages,$this->currentPage,$this->pages[$n]);
        $this->_putstreamobject($this->pages[$n]);
    }

    protected function _putpages() {
        $nb = $this->currentPage;
        for($n=1;$n<=$nb;$n++)
            $this->pageInfo[$n]['n'] = $this->currentObject+1+2*($n-1);
        for($n=1;$n<=$nb;$n++)
            $this->_putpage($n);
        // Pages root
        $this->_newobj(1);
        $this->_put('<</Type /Pages');
        $kids = '/Kids [';
        for($n=1;$n<=$nb;$n++)
            $kids .= $this->pageInfo[$n]['n'].' 0 R ';
        $this->_put($kids.']');
        $this->_put('/Count '.$nb);
        if($this->defaultOrientation=='P') {
            $w = $this->defaultPageSize[0];
            $h = $this->defaultPageSize[1];
        }
        else
        {
            $w = $this->defaultPageSize[1];
            $h = $this->defaultPageSize[0];
        }
        $this->_put(sprintf('/MediaBox [0 0 %.2F %.2F]',$w*$this->scale,$h*$this->scale));
        $this->_put('>>');
        $this->_put('endobj');
    }

    protected function _putfonts() {
        foreach($this->fontFiles as $file=>$info) {
            // Font file embedding
            $this->_newobj();
            $this->fontFiles[$file]['n'] = $this->currentObject;
            $font = file_get_contents($this->fontPath.$file,true);
            if(!$font)
                $this->Error('Font file not found: '.$file);
            $compressed = (substr($file,-2)=='.z');
            if(!$compressed && isset($info['length2']))
                $font = substr($font,6,$info['length1']).substr($font,6+$info['length1']+6,$info['length2']);
            $this->_put('<</Length '.strlen($font));
            if($compressed)
                $this->_put('/Filter /FlateDecode');
            $this->_put('/Length1 '.$info['length1']);
            if(isset($info['length2']))
                $this->_put('/Length2 '.$info['length2'].' /Length3 0');
            $this->_put('>>');
            $this->_putstream($font);
            $this->_put('endobj');
        }
        foreach($this->fonts as $k=>$font) {
            // Encoding
            if(isset($font['diff'])) {
                if(!isset($this->encodings[$font['enc']])) {
                    $this->_newobj();
                    $this->_put('<</Type /Encoding /BaseEncoding /WinAnsiEncoding /Differences ['.$font['diff'].']>>');
                    $this->_put('endobj');
                    $this->encodings[$font['enc']] = $this->currentObject;
                }
            }
            // ToUnicode CMap
            if(isset($font['uv'])) {
                if(isset($font['enc']))
                    $cmapkey = $font['enc'];
                else
                    $cmapkey = $font['name'];
                if(!isset($this->characterMaps[$cmapkey])) {
                    $cmap = $this->_tounicodecmap($font['uv']);
                    $this->_putstreamobject($cmap);
                    $this->characterMaps[$cmapkey] = $this->currentObject;
                }
            }
            // Font object
            $this->fonts[$k]['n'] = $this->currentObject+1;
            $type = $font['type'];
            $name = $font['name'];
            if($font['subsetted'])
                $name = 'AAAAAA+'.$name;
            if($type=='Core') {
                // Core font
                $this->_newobj();
                $this->_put('<</Type /Font');
                $this->_put('/BaseFont /'.$name);
                $this->_put('/Subtype /Type1');
                if($name!='Symbol' && $name!='ZapfDingbats')
                    $this->_put('/Encoding /WinAnsiEncoding');
                if(isset($font['uv']))
                    $this->_put('/ToUnicode '.$this->characterMaps[$cmapkey].' 0 R');
                $this->_put('>>');
                $this->_put('endobj');
            }
            elseif($type=='Type1' || $type=='TrueType') {
                // Additional Type1 or TrueType/OpenType font
                $this->_newobj();
                $this->_put('<</Type /Font');
                $this->_put('/BaseFont /'.$name);
                $this->_put('/Subtype /'.$type);
                $this->_put('/FirstChar 32 /LastChar 255');
                $this->_put('/Widths '.($this->currentObject+1).' 0 R');
                $this->_put('/FontDescriptor '.($this->currentObject+2).' 0 R');
                if(isset($font['diff']))
                    $this->_put('/Encoding '.$this->encodings[$font['enc']].' 0 R');
                else
                    $this->_put('/Encoding /WinAnsiEncoding');
                if(isset($font['uv']))
                    $this->_put('/ToUnicode '.$this->characterMaps[$cmapkey].' 0 R');
                $this->_put('>>');
                $this->_put('endobj');
                // Widths
                $this->_newobj();
                $cw = &$font['cw'];
                $s = '[';
                for($i=32;$i<=255;$i++)
                    $s .= $cw[chr($i)].' ';
                $this->_put($s.']');
                $this->_put('endobj');
                // Descriptor
                $this->_newobj();
                $s = '<</Type /FontDescriptor /FontName /'.$name;
                foreach($font['desc'] as $k=>$v)
                    $s .= ' /'.$k.' '.$v;
                if(!empty($font['file']))
                    $s .= ' /FontFile'.($type=='Type1' ? '' : '2').' '.$this->fontFiles[$font['file']]['n'].' 0 R';
                $this->_put($s.'>>');
                $this->_put('endobj');
            }
            else {
                // Allow for additional types
                $mtd = '_put'.strtolower($type);
                if(!method_exists($this,$mtd))
                    $this->Error('Unsupported font type: '.$type);
                $this->$mtd($font);
            }
        }
    }

    protected function _tounicodecmap($uv) {
        $ranges = '';
        $nbr = 0;
        $chars = '';
        $nbc = 0;
        foreach($uv as $c=>$v) {
            if(is_array($v)) {
                $ranges .= sprintf("<%02X> <%02X> <%04X>\n",$c,$c+$v[1]-1,$v[0]);
                $nbr++;
            }
            else {
                $chars .= sprintf("<%02X> <%04X>\n",$c,$v);
                $nbc++;
            }
        }
        $s = "/CIDInit /ProcSet findresource begin\n";
        $s .= "12 dict begin\n";
        $s .= "begincmap\n";
        $s .= "/CIDSystemInfo\n";
        $s .= "<</Registry (Adobe)\n";
        $s .= "/Ordering (UCS)\n";
        $s .= "/Supplement 0\n";
        $s .= ">> def\n";
        $s .= "/CMapName /Adobe-Identity-UCS def\n";
        $s .= "/CMapType 2 def\n";
        $s .= "1 begincodespacerange\n";
        $s .= "<00> <FF>\n";
        $s .= "endcodespacerange\n";
        if($nbr>0) {
            $s .= "$nbr beginbfrange\n";
            $s .= $ranges;
            $s .= "endbfrange\n";
        }
        if($nbc>0) {
            $s .= "$nbc beginbfchar\n";
            $s .= $chars;
            $s .= "endbfchar\n";
        }
        $s .= "endcmap\n";
        $s .= "CMapName currentdict /CMap defineresource pop\n";
        $s .= "end\n";
        $s .= "end";
        return $s;
    }

    protected function _putimages() {
        foreach(array_keys($this->images) as $file) {
            $this->_putimage($this->images[$file]);
            unset($this->images[$file]['data']);
            unset($this->images[$file]['smask']);
        }
    }

    protected function _putimage(&$info) {
        $this->_newobj();
        $info['n'] = $this->currentObject;
        $this->_put('<</Type /XObject');
        $this->_put('/Subtype /Image');
        $this->_put('/Width '.$info['w']);
        $this->_put('/Height '.$info['h']);
        if($info['cs']=='Indexed')
            $this->_put('/ColorSpace [/Indexed /DeviceRGB '.(strlen($info['pal'])/3-1).' '.($this->currentObject+1).' 0 R]');
        else
        {
            $this->_put('/ColorSpace /'.$info['cs']);
            if($info['cs']=='DeviceCMYK')
                $this->_put('/Decode [1 0 1 0 1 0 1 0]');
        }
        $this->_put('/BitsPerComponent '.$info['bpc']);
        if(isset($info['f']))
            $this->_put('/Filter /'.$info['f']);
        if(isset($info['dp']))
            $this->_put('/DecodeParms <<'.$info['dp'].'>>');
        if(isset($info['trns']) && is_array($info['trns'])) {
            $trns = '';
            for($i=0;$i<count($info['trns']);$i++)
                $trns .= $info['trns'][$i].' '.$info['trns'][$i].' ';
            $this->_put('/Mask ['.$trns.']');
        }
        if(isset($info['smask']))
            $this->_put('/SMask '.($this->currentObject+1).' 0 R');
        $this->_put('/Length '.strlen($info['data']).'>>');
        $this->_putstream($info['data']);
        $this->_put('endobj');
        // Soft mask
        if(isset($info['smask'])) {
            $dp = '/Predictor 15 /Colors 1 /BitsPerComponent 8 /Columns '.$info['w'];
            $smask = array('w'=>$info['w'], 'h'=>$info['h'], 'cs'=>'DeviceGray', 'bpc'=>8, 'f'=>$info['f'], 'dp'=>$dp, 'data'=>$info['smask']);
            $this->_putimage($smask);
        }
        // Palette
        if($info['cs']=='Indexed')
            $this->_putstreamobject($info['pal']);
    }

    protected function _putxobjectdict() {
        foreach($this->images as $image)
            $this->_put('/I'.$image['i'].' '.$image['n'].' 0 R');
    }

    protected function _putresourcedict() {
        $this->_put('/ProcSet [/PDF /Text /ImageB /ImageC /ImageI]');
        $this->_put('/Font <<');
        foreach($this->fonts as $font)
            $this->_put('/F'.$font['i'].' '.$font['n'].' 0 R');
        $this->_put('>>');
        $this->_put('/XObject <<');
        $this->_putxobjectdict();
        $this->_put('>>');
    }

    protected function _putresources() {
        $this->_putfonts();
        $this->_putimages();
        // Resource dictionary
        $this->_newobj(2);
        $this->_put('<<');
        $this->_putresourcedict();
        $this->_put('>>');
        $this->_put('endobj');
    }

    protected function _putinfo() {
        $this->metadata['Producer'] = 'PHPDF '.PHPDF_VERSION;
        $this->metadata['CreationDate'] = 'D:'.@date('YmdHis');
        foreach($this->metadata as $key=>$value)
            $this->_put('/'.$key.' '.$this->_textstring($value));
    }

    protected function _putcatalog() {
        $n = $this->pageInfo[1]['n'];
        $this->_put('/Type /Catalog');
        $this->_put('/Pages 1 0 R');
        if($this->zoomMode=='fullpage')
            $this->_put('/OpenAction ['.$n.' 0 R /Fit]');
        elseif($this->zoomMode=='fullwidth')
            $this->_put('/OpenAction ['.$n.' 0 R /FitH null]');
        elseif($this->zoomMode=='real')
            $this->_put('/OpenAction ['.$n.' 0 R /XYZ null null 1]');
        elseif(!is_string($this->zoomMode))
            $this->_put('/OpenAction ['.$n.' 0 R /XYZ null null '.sprintf('%.2F',$this->zoomMode/100).']');
        if($this->layoutMode=='single')
            $this->_put('/PageLayout /SinglePage');
        elseif($this->layoutMode=='continuous')
            $this->_put('/PageLayout /OneColumn');
        elseif($this->layoutMode=='two')
            $this->_put('/PageLayout /TwoColumnLeft');
    }

    protected function _putheader() {
        $this->_put('%PDF-'.$this->PDFVersion);
    }

    protected function _puttrailer() {
        $this->_put('/Size '.($this->currentObject+1));
        $this->_put('/Root '.$this->currentObject.' 0 R');
        $this->_put('/Info '.($this->currentObject-1).' 0 R');
    }

    protected function _enddoc() {
        $this->_putheader();
        $this->_putpages();
        $this->_putresources();
        // Info
        $this->_newobj();
        $this->_put('<<');
        $this->_putinfo();
        $this->_put('>>');
        $this->_put('endobj');
        // Catalog
        $this->_newobj();
        $this->_put('<<');
        $this->_putcatalog();
        $this->_put('>>');
        $this->_put('endobj');
        // Cross-ref
        $offset = $this->_getoffset();
        $this->_put('xref');
        $this->_put('0 '.($this->currentObject+1));
        $this->_put('0000000000 65535 f ');
        for($i=1;$i<=$this->currentObject;$i++)
            $this->_put(sprintf('%010d 00000 n ',$this->offsets[$i]));
        // Trailer
        $this->_put('trailer');
        $this->_put('<<');
        $this->_puttrailer();
        $this->_put('>>');
        $this->_put('startxref');
        $this->_put($offset);
        $this->_put('%%EOF');
        $this->state = 3;
    }
}