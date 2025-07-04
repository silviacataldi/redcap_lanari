<?php
/*******************************************************************************
* tFPDF (based on FPDF 1.7)                                                    *
*                                                                              *
* Version:  1.22                                                               *
* Date:     2011-07-30                                                         *
* Author:   Ian Back <ianb@bpm1.com>                                           *
* License:  LGPL                                                               *
*******************************************************************************/

define('tFPDF_VERSION','1.22');

class FPDF
{
	var $unifontSubset;
	var $page;               // current page number
	var $n;                  // current object number
	var $offsets;            // array of object offsets
	var $buffer;             // buffer holding in-memory PDF
	var $pages;              // array containing pages
	var $state;              // current document state
	var $compress;           // compression flag
	var $k;                  // scale factor (number of points in user unit)
	var $DefOrientation;     // default orientation
	var $CurOrientation;     // current orientation
	var $StdPageSizes;       // standard page sizes
	var $DefPageSize;        // default page size
	var $CurPageSize;        // current page size
	var $PageSizes;          // used for pages with non default sizes or orientations
	var $wPt, $hPt;          // dimensions of current page in points
	var $w, $h;              // dimensions of current page in user unit
	var $lMargin;            // left margin
	var $tMargin;            // top margin
	var $rMargin;            // right margin
	var $bMargin;            // page break margin
	var $cMargin;            // cell margin
	var $x, $y;              // current position in user unit
	var $lasth;              // height of last printed cell
	var $LineWidth;          // line width in user unit
	var $fontpath;           // path containing fonts
	var $CoreFonts;          // array of core font names
	var $fonts;              // array of used fonts
	var $FontFiles;          // array of font files
	var $diffs;              // array of encoding differences
	var $FontFamily;         // current font family
	var $FontStyle;          // current font style
	var $underline;          // underlining flag
	var $CurrentFont;        // current font info
	var $FontSizePt;         // current font size in points
	var $FontSize;           // current font size in user unit
	var $DrawColor;          // commands for drawing color
	var $FillColor;          // commands for filling color
	var $TextColor;          // commands for text color
	var $ColorFlag;          // indicates whether fill and text colors are different
	var $ws;                 // word spacing
	var $images;             // array of used images
	var $PageLinks;          // array of links in pages
	var $links;              // array of internal links
	var $AutoPageBreak;      // automatic page breaking
	var $PageBreakTrigger;   // threshold used to trigger page breaks
	var $InHeader;           // flag set when processing header
	var $InFooter;           // flag set when processing footer
	var $ZoomMode;           // zoom display mode
	var $LayoutMode;         // layout display mode
	var $title;              // title
	var $subject;            // subject
	var $author;             // author
	var $keywords;           // keywords
	var $creator;            // creator
	var $AliasNbPages;       // alias for total number of pages
	var $PDFVersion;         // PDF version number

	/*******************************************************************************
	*                                                                              *
	*                               Public methods                                 *
	*                                                                              *
	*******************************************************************************/
	function __construct($orientation='P', $unit='mm', $size='A4')
	{
		// Some checks
		$this->_dochecks();
		// Initialization of properties
		$this->page = 0;
		$this->n = 2;
		$this->buffer = '';
		$this->pages = array();
		$this->PageSizes = array();
		$this->state = 0;
		$this->fonts = array();
		$this->FontFiles = array();
		$this->diffs = array();
		$this->images = array();
		$this->links = array();
		$this->InHeader = false;
		$this->InFooter = false;
		$this->lasth = 0;
		$this->FontFamily = '';
		$this->FontStyle = '';
		$this->FontSizePt = 12;
		$this->underline = false;
		$this->DrawColor = '0 G';
		$this->FillColor = '0 g';
		$this->TextColor = '0 g';
		$this->ColorFlag = false;
		$this->ws = 0;
		// Font path
		if(defined('FPDF_FONTPATH'))
		{
			$this->fontpath = FPDF_FONTPATH;
			if(substr($this->fontpath,-1)!='/' && substr($this->fontpath,-1)!='\\')
				$this->fontpath .= '/';
		}
		elseif(is_dir(dirname(__FILE__).'/font'))
			$this->fontpath = dirname(__FILE__).'/font/';
		else
			$this->fontpath = '';
		// Core fonts
		$this->CoreFonts = array('courier', 'helvetica', 'times', 'symbol', 'zapfdingbats');
		// Scale factor
		if($unit=='pt')
			$this->k = 1;
		elseif($unit=='mm')
			$this->k = 72/25.4;
		elseif($unit=='cm')
			$this->k = 72/2.54;
		elseif($unit=='in')
			$this->k = 72;
		else
			$this->Error('Incorrect unit: '.$unit);
		// Page sizes
		$this->StdPageSizes = array('a3'=>array(841.89,1190.55), 'a4'=>array(595.28,841.89), 'a5'=>array(420.94,595.28),
			'letter'=>array(612,792), 'legal'=>array(612,1008));
		$size = $this->_getpagesize($size);
		$this->DefPageSize = $size;
		$this->CurPageSize = $size;
		// Page orientation
		$orientation = strtolower($orientation);
		if($orientation=='p' || $orientation=='portrait')
		{
			$this->DefOrientation = 'P';
			$this->w = $size[0];
			$this->h = $size[1];
		}
		elseif($orientation=='l' || $orientation=='landscape')
		{
			$this->DefOrientation = 'L';
			$this->w = $size[1];
			$this->h = $size[0];
		}
		else
			$this->Error('Incorrect orientation: '.$orientation);
		$this->CurOrientation = $this->DefOrientation;
		$this->wPt = $this->w*$this->k;
		$this->hPt = $this->h*$this->k;
		// Page margins (1 cm)
		$margin = 28.35/$this->k;
		$this->SetMargins($margin,$margin);
		// Interior cell margin (1 mm)
		$this->cMargin = $margin/10;
		// Line width (0.2 mm)
		$this->LineWidth = .567/$this->k;
		// Automatic page break
		$this->SetAutoPageBreak(true,2*$margin);
		// Default display mode
		$this->SetDisplayMode('default');
		// Enable compression
		$this->SetCompression(true);
		// Set default PDF version number
		$this->PDFVersion = '1.3';
	}

	function SetMargins($left, $top, $right=null)
	{
		// Set left, top and right margins
		$this->lMargin = $left;
		$this->tMargin = $top;
		if($right===null)
			$right = $left;
		$this->rMargin = $right;
	}

	function SetLeftMargin($margin)
	{
		// Set left margin
		$this->lMargin = $margin;
		if($this->page>0 && $this->x<$margin)
			$this->x = $margin;
	}

	function SetTopMargin($margin)
	{
		// Set top margin
		$this->tMargin = $margin;
	}

	function SetRightMargin($margin)
	{
		// Set right margin
		$this->rMargin = $margin;
	}

	function SetAutoPageBreak($auto, $margin=0)
	{
		// Set auto page break mode and triggering margin
		$this->AutoPageBreak = $auto;
		$this->bMargin = $margin;
		$this->PageBreakTrigger = $this->h-$margin;
	}

	function SetDisplayMode($zoom, $layout='default')
	{
		// Set display mode in viewer
		if($zoom=='fullpage' || $zoom=='fullwidth' || $zoom=='real' || $zoom=='default' || !is_string($zoom))
			$this->ZoomMode = $zoom;
		else
			$this->Error('Incorrect zoom display mode: '.$zoom);
		if($layout=='single' || $layout=='continuous' || $layout=='two' || $layout=='default')
			$this->LayoutMode = $layout;
		else
			$this->Error('Incorrect layout display mode: '.$layout);
	}

	function SetCompression($compress)
	{
		// Set page compression
		if(function_exists('gzcompress'))
			$this->compress = $compress;
		else
			$this->compress = false;
	}

	function SetTitle($title, $isUTF8=false)
	{
		// Title of document
		if($isUTF8)
			$title = $this->_UTF8toUTF16($title);
		$this->title = $title;
	}

	function SetSubject($subject, $isUTF8=false)
	{
		// Subject of document
		if($isUTF8)
			$subject = $this->_UTF8toUTF16($subject);
		$this->subject = $subject;
	}

	function SetAuthor($author, $isUTF8=false)
	{
		// Author of document
		if($isUTF8)
			$author = $this->_UTF8toUTF16($author);
		$this->author = $author;
	}

	function SetKeywords($keywords, $isUTF8=false)
	{
		// Keywords of document
		if($isUTF8)
			$keywords = $this->_UTF8toUTF16($keywords);
		$this->keywords = $keywords;
	}

	function SetCreator($creator, $isUTF8=false)
	{
		// Creator of document
		if($isUTF8)
			$creator = $this->_UTF8toUTF16($creator);
		$this->creator = $creator;
	}

	function AliasNbPages($alias='{nb}')
	{
		// Define an alias for total number of pages
		$this->AliasNbPages = $alias;
	}

	function Error($msg)
	{
		// Fatal error
		//die('<b>FPDF error:</b> '.$msg);
	}

	function Open()
	{
		// Begin document
		$this->state = 1;
	}

	function Close()
	{
		// Terminate document
		if($this->state==3)
			return;
		if($this->page==0)
			$this->AddPage();
		// Page footer
		$this->InFooter = true;
		$this->Footer();
		$this->InFooter = false;
		// Close page
		$this->_endpage();
		// Close document
		$this->_enddoc();
	}

	function AddPage($orientation='', $size='')
	{
		// Start a new page
		if($this->state==0)
			$this->Open();
		$family = $this->FontFamily;
		$style = $this->FontStyle.($this->underline ? 'U' : '');
		$fontsize = $this->FontSizePt;
		$lw = $this->LineWidth;
		$dc = $this->DrawColor;
		$fc = $this->FillColor;
		$tc = $this->TextColor;
		$cf = $this->ColorFlag;
		if($this->page>0)
		{
			// Page footer
			$this->InFooter = true;
			$this->Footer();
			$this->InFooter = false;
			// Close page
			$this->_endpage();
		}
		// Start new page
		$this->_beginpage($orientation,$size);
		// Set line cap style to square
		$this->_out('2 J');
		// Set line width
		$this->LineWidth = $lw;
		$this->_out(sprintf('%.2F w',$lw*$this->k));
		// Set font
		if($family)
			$this->SetFont($family,$style,$fontsize);
		// Set colors
		$this->DrawColor = $dc;
		if($dc!='0 G')
			$this->_out($dc);
		$this->FillColor = $fc;
		if($fc!='0 g')
			$this->_out($fc);
		$this->TextColor = $tc;
		$this->ColorFlag = $cf;
		// Page header
		$this->InHeader = true;
		$this->Header();
		$this->InHeader = false;
		// Restore line width
		if($this->LineWidth!=$lw)
		{
			$this->LineWidth = $lw;
			$this->_out(sprintf('%.2F w',$lw*$this->k));
		}
		// Restore font
		if($family)
			$this->SetFont($family,$style,$fontsize);
		// Restore colors
		if($this->DrawColor!=$dc)
		{
			$this->DrawColor = $dc;
			$this->_out($dc);
		}
		if($this->FillColor!=$fc)
		{
			$this->FillColor = $fc;
			$this->_out($fc);
		}
		$this->TextColor = $tc;
		$this->ColorFlag = $cf;
	}

	function Header()
	{
		// To be implemented in your own inherited class
	}

	function Footer()
	{
		// To be implemented in your own inherited class
	}

	function PageNo()
	{
		// Get current page number
		return $this->page;
	}

	function SetDrawColor($r, $g=null, $b=null)
	{
		// Set color for all stroking operations
		if(($r==0 && $g==0 && $b==0) || $g===null)
			$this->DrawColor = sprintf('%.3F G',$r/255);
		else
			$this->DrawColor = sprintf('%.3F %.3F %.3F RG',$r/255,$g/255,$b/255);
		if($this->page>0)
			$this->_out($this->DrawColor);
	}

	function SetFillColor($r, $g=null, $b=null)
	{
		// Set color for all filling operations
		if(($r==0 && $g==0 && $b==0) || $g===null)
			$this->FillColor = sprintf('%.3F g',$r/255);
		else
			$this->FillColor = sprintf('%.3F %.3F %.3F rg',$r/255,$g/255,$b/255);
		$this->ColorFlag = ($this->FillColor!=$this->TextColor);
		if($this->page>0)
			$this->_out($this->FillColor);
	}

	function SetTextColor($r, $g=null, $b=null)
	{
		// Set color for text
		if(($r==0 && $g==0 && $b==0) || $g===null)
			$this->TextColor = sprintf('%.3F g',$r/255);
		else
			$this->TextColor = sprintf('%.3F %.3F %.3F rg',$r/255,$g/255,$b/255);
		$this->ColorFlag = ($this->FillColor!=$this->TextColor);
	}

	function GetStringWidth($s)
	{
		// Get width of a string in the current font
		$s = (string)$s;
		$cw = &$this->CurrentFont['cw'];
		$w=0;
		if ($this->unifontSubset) {
			$unicode = $this->UTF8StringToArray($s);
			foreach($unicode as $char) {
				if (isset($cw[$char])) { $w += (ord($cw[2*$char])<<8) + ord($cw[2*$char+1]); }
				else if($char>0 && $char<128 && isset($cw[chr($char)])) { $w += $cw[chr($char)]; }
				else if(isset($this->CurrentFont['desc']['MissingWidth'])) { $w += $this->CurrentFont['desc']['MissingWidth']; }
				else if(isset($this->CurrentFont['MissingWidth'])) { $w += $this->CurrentFont['MissingWidth']; }
				else { $w += 500; }
			}
		}
		else {
			$l = strlen($s);
			for($i=0;$i<$l;$i++)
				$w += $cw[$s[$i]];
		}
		return $w*$this->FontSize/1000;
	}

	function SetLineWidth($width)
	{
		// Set line width
		$this->LineWidth = $width;
		if($this->page>0)
			$this->_out(sprintf('%.2F w',$width*$this->k));
	}

	function Line($x1, $y1, $x2, $y2)
	{
		// Draw a line
		$this->_out(sprintf('%.2F %.2F m %.2F %.2F l S',$x1*$this->k,($this->h-$y1)*$this->k,$x2*$this->k,($this->h-$y2)*$this->k));
	}

	function Rect($x, $y, $w, $h, $style='')
	{
		// Draw a rectangle
		if($style=='F')
			$op = 'f';
		elseif($style=='FD' || $style=='DF')
			$op = 'B';
		else
			$op = 'S';
		$this->_out(sprintf('%.2F %.2F %.2F %.2F re %s',$x*$this->k,($this->h-$y)*$this->k,$w*$this->k,-$h*$this->k,$op));
	}

	function AddFont($family, $style='', $file='', $uni=false)
	{
		// Add a TrueType, OpenType or Type1 font
		$family = strtolower($family);
		$style = strtoupper($style);
		if($style=='IB')
			$style='BI';
		if($file=='') {
		   if ($uni) {
			$file = str_replace(' ','',$family).strtolower($style).'.ttf';
		   }
		   else {
			$file = str_replace(' ','',$family).strtolower($style).'.php';
		   }
		}
		$fontkey = $family.$style;
		if(isset($this->fonts[$fontkey]))
			return;
		if ($uni) {
			if (defined("_SYSTEM_TTFONTS") && file_exists(_SYSTEM_TTFONTS.$file )) { $ttffilename = _SYSTEM_TTFONTS.$file ; }
			else { $ttffilename = $this->_getfontpath().'unifont/'.$file ; }
			$unifilename = $this->_getfontpath().'unifont/'.strtolower(substr($file ,0,(strpos($file ,'.'))));
			$name = '';
			$originalsize = 0;
			$ttfstat = stat($ttffilename);
			if (file_exists($unifilename.'.mtx.php')) {
				include($unifilename.'.mtx.php');
			}
			if (!isset($type) ||  !isset($name) || $originalsize != $ttfstat['size']) {
				$ttffile = $ttffilename;
				if (!class_exists('TTFontFile', false)) { include($this->_getfontpath().'unifont/ttfonts.php'); }
				$ttf = new TTFontFile();
				$ttf->getMetrics($ttffile);
				$cw = $ttf->charWidths;
				$name = preg_replace('/[ ()]/','',$ttf->fullName);

				$desc= array('Ascent'=>round($ttf->ascent),
				'Descent'=>round($ttf->descent),
				'CapHeight'=>round($ttf->capHeight),
				'Flags'=>$ttf->flags,
				'FontBBox'=>'['.round($ttf->bbox[0])." ".round($ttf->bbox[1])." ".round($ttf->bbox[2])." ".round($ttf->bbox[3]).']',
				'ItalicAngle'=>$ttf->italicAngle,
				'StemV'=>round($ttf->stemV),
				'MissingWidth'=>round($ttf->defaultWidth));
				$up = round($ttf->underlinePosition);
				$ut = round($ttf->underlineThickness);
				$originalsize = $ttfstat['size']+0;
				$type = 'TTF';
				// Generate metrics .php file
				$s='<?php'."\n";
				$s.='$name=\''.$name."';\n";
				$s.='$type=\''.$type."';\n";
				$s.='$desc='.var_export($desc,true).";\n";
				$s.='$up='.$up.";\n";
				$s.='$ut='.$ut.";\n";
				$s.='$ttffile=dirname(__FILE__).DIRECTORY_SEPARATOR.\''.basename($ttffile)."';\n";
				$s.='$originalsize='.$originalsize.";\n";
				$s.='$fontkey=\''.$fontkey."';\n";
				$s.="?>";
				if (is_writable(dirname($this->_getfontpath().'unifont/'.'x'))) {
					$fh = fopen($unifilename.'.mtx.php',"w");
					fwrite($fh,$s,strlen($s));
					fclose($fh);
					$fh = fopen($unifilename.'.cw.dat',"wb");
					fwrite($fh,$cw,strlen($cw));
					fclose($fh);
					@unlink($unifilename.'.cw127.php');
				}
				unset($ttf);
			}
			else {
				$cw = @file_get_contents($unifilename.'.cw.dat'); 
			}
			$i = count($this->fonts)+1;
			if(!empty($this->AliasNbPages))
				$sbarr = range(0,57);
			else
				$sbarr = range(0,32);
			$this->fonts[$fontkey] = array('i'=>$i, 'type'=>$type, 'name'=>$name, 'desc'=>$desc, 'up'=>$up, 'ut'=>$ut, 'cw'=>$cw, 'ttffile'=>$ttffile, 'fontkey'=>$fontkey, 'subset'=>$sbarr, 'unifilename'=>$unifilename);

			$this->FontFiles[$fontkey]=array('length1'=>$originalsize, 'type'=>"TTF", 'ttffile'=>$ttffile);
			$this->FontFiles[$file]=array('type'=>"TTF");
			unset($cw);
		}
		else {
			$info = $this->_loadfont($file);
			$info['i'] = count($this->fonts)+1;
			if(!empty($info['diff']))
			{
				// Search existing encodings
				$n = array_search($info['diff'],$this->diffs);
				if(!$n)
				{
					$n = count($this->diffs)+1;
					$this->diffs[$n] = $info['diff'];
				}
				$info['diffn'] = $n;
			}
			if(!empty($info['file']))
			{
				// Embedded font
				if($info['type']=='TrueType')
					$this->FontFiles[$info['file']] = array('length1'=>$info['originalsize']);
				else
					$this->FontFiles[$info['file']] = array('length1'=>$info['size1'], 'length2'=>$info['size2']);
			}
			$this->fonts[$fontkey] = $info;
		}
	}

	function SetFont($family, $style='', $size=0)
	{
		// Select a font; size given in points
		if($family=='')
			$family = $this->FontFamily;
		else
			$family = strtolower($family);
		$style = strtoupper($style);
		if(strpos($style,'U')!==false)
		{
			$this->underline = true;
			$style = str_replace('U','',$style);
		}
		else
			$this->underline = false;
		if($style=='IB')
			$style = 'BI';
		if($size==0)
			$size = $this->FontSizePt;
		// Test if font is already selected
		if($this->FontFamily==$family && $this->FontStyle==$style && $this->FontSizePt==$size)
			return;
		// Test if font is already loaded
		$fontkey = $family.$style;
		if(!isset($this->fonts[$fontkey]))
		{
			// Test if one of the core fonts
			if($family=='arial')
				$family = 'helvetica';
			if(in_array($family,$this->CoreFonts))
			{
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
		$this->FontFamily = $family;
		$this->FontStyle = $style;
		$this->FontSizePt = $size;
		$this->FontSize = $size/$this->k;
		$this->CurrentFont = &$this->fonts[$fontkey];
		if ($this->fonts[$fontkey]['type']=='TTF') { $this->unifontSubset = true; }
		else { $this->unifontSubset = false; }
		if($this->page>0)
			$this->_out(sprintf('BT /F%d %.2F Tf ET',$this->CurrentFont['i'],$this->FontSizePt));
	}

	function SetFontSize($size)
	{
		// Set font size in points
		if($this->FontSizePt==$size)
			return;
		$this->FontSizePt = $size;
		$this->FontSize = $size/$this->k;
		if($this->page>0)
			$this->_out(sprintf('BT /F%d %.2F Tf ET',$this->CurrentFont['i'],$this->FontSizePt));
	}

	function AddLink()
	{
		// Create a new internal link
		$n = count($this->links)+1;
		$this->links[$n] = array(0, 0);
		return $n;
	}

	function SetLink($link, $y=0, $page=-1)
	{
		// Set destination of internal link
		if($y==-1)
			$y = $this->y;
		if($page==-1)
			$page = $this->page;
		$this->links[$link] = array($page, $y);
	}

	function Link($x, $y, $w, $h, $link)
	{
		// Put a link on the page
		$this->PageLinks[$this->page][] = array($x*$this->k, $this->hPt-$y*$this->k, $w*$this->k, $h*$this->k, $link);
	}

	function Text($x, $y, $txt)
	{
		// Output a string
		if ($this->unifontSubset)
		{
			$txt2 = '('.$this->_escape($this->UTF8ToUTF16BE($txt, false)).')';
			foreach($this->UTF8StringToArray($txt) as $uni)
				$this->CurrentFont['subset'][$uni] = $uni;
		}
		else 
			$txt2 = '('.$this->_escape($txt).')';
		$s = sprintf('BT %.2F %.2F Td %s Tj ET',$x*$this->k,($this->h-$y)*$this->k,$txt2);
		if($this->underline && $txt!='')
			$s .= ' '.$this->_dounderline($x,$y,$txt);
		if($this->ColorFlag)
			$s = 'q '.$this->TextColor.' '.$s.' Q';
		$this->_out($s);
	}

	function AcceptPageBreak()
	{
		// Accept automatic page break or not
		return $this->AutoPageBreak;
	}

	function Cell($w, $h=0, $txt='', $border=0, $ln=0, $align='', $fill=false, $link='')
	{
		// Output a cell
		$k = $this->k;
		if($this->y+$h>$this->PageBreakTrigger && !$this->InHeader && !$this->InFooter && $this->AcceptPageBreak())
		{
			// Automatic page break
			$x = $this->x;
			$ws = $this->ws;
			if($ws>0)
			{
				$this->ws = 0;
				$this->_out('0 Tw');
			}
			$this->AddPage($this->CurOrientation,$this->CurPageSize);
			$this->x = $x;
			if($ws>0)
			{
				$this->ws = $ws;
				$this->_out(sprintf('%.3F Tw',$ws*$k));
			}
		}
		if($w==0)
			$w = $this->w-$this->rMargin-$this->x;
		$s = '';
		if($fill || $border==1)
		{
			if($fill)
				$op = ($border==1) ? 'B' : 'f';
			else
				$op = 'S';
			$s = sprintf('%.2F %.2F %.2F %.2F re %s ',$this->x*$k,($this->h-$this->y)*$k,$w*$k,-$h*$k,$op);
		}
		if(is_string($border))
		{
			$x = $this->x;
			$y = $this->y;
			if(strpos($border,'L')!==false)
				$s .= sprintf('%.2F %.2F m %.2F %.2F l S ',$x*$k,($this->h-$y)*$k,$x*$k,($this->h-($y+$h))*$k);
			if(strpos($border,'T')!==false)
				$s .= sprintf('%.2F %.2F m %.2F %.2F l S ',$x*$k,($this->h-$y)*$k,($x+$w)*$k,($this->h-$y)*$k);
			if(strpos($border,'R')!==false)
				$s .= sprintf('%.2F %.2F m %.2F %.2F l S ',($x+$w)*$k,($this->h-$y)*$k,($x+$w)*$k,($this->h-($y+$h))*$k);
			if(strpos($border,'B')!==false)
				$s .= sprintf('%.2F %.2F m %.2F %.2F l S ',$x*$k,($this->h-($y+$h))*$k,($x+$w)*$k,($this->h-($y+$h))*$k);
		}
		if($txt!=='')
		{
			if($align=='R')
				$dx = $w-$this->cMargin-$this->GetStringWidth($txt);
			elseif($align=='C')
				$dx = ($w-$this->GetStringWidth($txt))/2;
			else
				$dx = $this->cMargin;
			if($this->ColorFlag)
				$s .= 'q '.$this->TextColor.' ';
			if ($this->unifontSubset)
			{
				$txt2 = '('.$this->_escape($this->UTF8ToUTF16BE($txt, false)).')';
				foreach($this->UTF8StringToArray($txt) as $uni)
					$this->CurrentFont['subset'][$uni] = $uni;
			}
			else
				$txt2='('.str_replace(')','\\)',str_replace('(','\\(',str_replace('\\','\\\\',$txt))).')';
			$s .= sprintf('BT %.2F %.2F Td %s Tj ET',($this->x+$dx)*$k,($this->h-($this->y+.5*$h+.3*$this->FontSize))*$k,$txt2);
			if($this->underline)
				$s .= ' '.$this->_dounderline($this->x+$dx,$this->y+.5*$h+.3*$this->FontSize,$txt);
			if($this->ColorFlag)
				$s .= ' Q';
			if($link)
				$this->Link($this->x+$dx,$this->y+.5*$h-.5*$this->FontSize,$this->GetStringWidth($txt),$this->FontSize,$link);
		}
		if($s)
			$this->_out($s);
		$this->lasth = $h;
		if($ln>0)
		{
			// Go to next line
			$this->y += $h;
			if($ln==1)
				$this->x = $this->lMargin;
		}
		else
			$this->x += $w;
	}

	function MultiCell($w, $h, $txt, $border=0, $align='J', $fill=false)
	{
		// Output text with automatic or explicit line breaks
		$cw = &$this->CurrentFont['cw'];
		if($w==0)
			$w = $this->w-$this->rMargin-$this->x;
		$wmax = ($w-2*$this->cMargin);
		$s = str_replace("\r",'',$txt);
		if ($this->unifontSubset) {
			$nb=mb_strlen($s, 'utf-8');
			while($nb>0 && mb_substr($s,$nb-1,1,'utf-8')=="\n")	$nb--;
		}
		else {
			$nb = strlen($s);
			if($nb>0 && $s[$nb-1]=="\n")
				$nb--;
		}
		$b = 0;
		if($border)
		{
			if($border==1)
			{
				$border = 'LTRB';
				$b = 'LRT';
				$b2 = 'LR';
			}
			else
			{
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
		while($i<$nb)
		{
			// Get next character
			if ($this->unifontSubset) {
				$c = mb_substr($s,$i,1,'UTF-8');
			}
			else {
				$c=$s[$i];
			}
			if($c=="\n")
			{
				// Explicit line break
				if($this->ws>0)
				{
					$this->ws = 0;
					$this->_out('0 Tw');
				}
				if ($this->unifontSubset) {
					$this->Cell($w,$h,mb_substr($s,$j,$i-$j,'UTF-8'),$b,2,$align,$fill);
				}
				else {
					$this->Cell($w,$h,substr($s,$j,$i-$j),$b,2,$align,$fill);
				}
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
			if($c==' ')
			{
				$sep = $i;
				$ls = $l;
				$ns++;
			}

			if ($this->unifontSubset) { $l += $this->GetStringWidth($c); }
			else { $l += $cw[$c]*$this->FontSize/1000; }

			if($l>$wmax)
			{
				// Automatic line break
				if($sep==-1)
				{
					if($i==$j)
						$i++;
					if($this->ws>0)
					{
						$this->ws = 0;
                        if ($this->GetY() > \PDF::bottom_of_page) \PDF::new_page_check(0, $this, $GLOBALS['study_id_event'], true); // REDCap custom code - add new page
						$this->_out('0 Tw');
					}
					if ($this->unifontSubset) {
						$this->Cell($w,$h,mb_substr($s,$j,$i-$j,'UTF-8'),$b,2,$align,$fill);
					}
					else {
						$this->Cell($w,$h,substr($s,$j,$i-$j),$b,2,$align,$fill);
					}
				}
				else
				{
					if($align=='J')
					{
						$this->ws = ($ns>1) ? ($wmax-$ls)/($ns-1) : 0;
                        if ($this->GetY() > \PDF::bottom_of_page) \PDF::new_page_check(0, $this, $GLOBALS['study_id_event'], true); // REDCap custom code - add new page
						$this->_out(sprintf('%.3F Tw',$this->ws*$this->k));
					}
					if ($this->unifontSubset) {
						$this->Cell($w,$h,mb_substr($s,$j,$sep-$j,'UTF-8'),$b,2,$align,$fill);
					}
					else {
						$this->Cell($w,$h,substr($s,$j,$sep-$j),$b,2,$align,$fill);
					}
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
		if($this->ws>0)
		{
			$this->ws = 0;
            if ($this->GetY() > \PDF::bottom_of_page) \PDF::new_page_check(0, $this, $GLOBALS['study_id_event'], true); // REDCap custom code - add new page
			$this->_out('0 Tw');
		}
		if($border && strpos($border,'B')!==false)
			$b .= 'B';
		if ($this->unifontSubset) {
			$this->Cell($w,$h,mb_substr($s,$j,$i-$j,'UTF-8'),$b,2,$align,$fill);
		}
		else {
			$this->Cell($w,$h,substr($s,$j,$i-$j),$b,2,$align,$fill);
		}
		$this->x = $this->lMargin;
	}

	function Write($h, $txt, $link='')
	{
		// Output text in flowing mode
		$cw = &$this->CurrentFont['cw'];
		$w = $this->w-$this->rMargin-$this->x;

		$wmax = ($w-2*$this->cMargin);
		$s = str_replace("\r",'',$txt);
		if ($this->unifontSubset) {
			$nb=mb_strlen($s, 'UTF-8');
			if($nb==1 && $s==" ") {
				$this->x += $this->GetStringWidth($s);
				return;
			}
		}
		else {
			$nb = strlen($s);
		}
		$sep = -1;
		$i = 0;
		$j = 0;
		$l = 0;
		$nl = 1;
		while($i<$nb)
		{
			// Get next character
			if ($this->unifontSubset) {
				$c = mb_substr($s,$i,1,'UTF-8');
			}
			else {
				$c = $s[$i];
			}
			if($c=="\n")
			{
				// Explicit line break
				if ($this->unifontSubset) {
					$this->Cell($w,$h,mb_substr($s,$j,$i-$j,'UTF-8'),0,2,'',0,$link);
				}
				else {
					$this->Cell($w,$h,substr($s,$j,$i-$j),0,2,'',0,$link);
				}
				$i++;
				$sep = -1;
				$j = $i;
				$l = 0;
				if($nl==1)
				{
					$this->x = $this->lMargin;
					$w = $this->w-$this->rMargin-$this->x;
					$wmax = ($w-2*$this->cMargin)*1000/$this->FontSize;
				}
				$nl++;
				continue;
			}
			if($c==' ')
				$sep = $i;

			if ($this->unifontSubset) { $l += $this->GetStringWidth($c); }
			else { $l += $cw[$c]*$this->FontSize/1000; }

			if($l>$wmax)
			{
				// Automatic line break
				if($sep==-1)
				{
					if($this->x>$this->lMargin)
					{
						// Move to next line
						$this->x = $this->lMargin;
						$this->y += $h;
						$w = $this->w-$this->rMargin-$this->x;
						$wmax = ($w-2*$this->cMargin)*1000/$this->FontSize;
						$i++;
						$nl++;
						continue;
					}
					if($i==$j)
						$i++;
					if ($this->unifontSubset) {
						$this->Cell($w,$h,mb_substr($s,$j,$i-$j,'UTF-8'),0,2,'',0,$link);
					}
					else {
						$this->Cell($w,$h,substr($s,$j,$i-$j),0,2,'',0,$link);
					}
				}
				else
				{
					if ($this->unifontSubset) {
						$this->Cell($w,$h,mb_substr($s,$j,$sep-$j,'UTF-8'),0,2,'',0,$link);
					}
					else {
						$this->Cell($w,$h,substr($s,$j,$sep-$j),0,2,'',0,$link);
					}
					$i = $sep+1;
				}
				$sep = -1;
				$j = $i;
				$l = 0;
				if($nl==1)
				{
					$this->x = $this->lMargin;
					$w = $this->w-$this->rMargin-$this->x;
					$wmax = ($w-2*$this->cMargin)*1000/$this->FontSize;
				}
				$nl++;
			}
			else
				$i++;
		}
		// Last chunk
		if($i!=$j) {
			if ($this->unifontSubset) {
				$this->Cell($l,$h,mb_substr($s,$j,$i-$j,'UTF-8'),0,0,'',0,$link);
			}
			else {
				$this->Cell($l,$h,substr($s,$j),0,0,'',0,$link);
			}
		}
	}

	function Ln($h=null)
	{
		// Line feed; default value is last cell height
		$this->x = $this->lMargin;
		if($h===null)
			$this->y += $this->lasth;
		else
			$this->y += $h;
	}

	function Image($file, $x=null, $y=null, $w=0, $h=0, $type='', $link='')
	{
		// Put an image on the page
		if(!isset($this->images[$file]))
		{
			// First use of this image, get info
			if($type=='')
			{
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
				return; //$this->Error('Unsupported image type: '.$type);
			$info = $this->$mtd($file);
			$info['i'] = count($this->images)+1;
			$this->images[$file] = $info;
		}
		else
			$info = $this->images[$file];

		// Automatic width and height calculation if needed
		if($w==0 && $h==0)
		{
			// Put image at 96 dpi
			$w = -96;
			$h = -96;
		}
		if($w<0)
			$w = -$info['w']*72/$w/$this->k;
		if($h<0)
			$h = -$info['h']*72/$h/$this->k;
		if($w==0)
			$w = $h*$info['w']/$info['h'];
		if($h==0)
			$h = $w*$info['h']/$info['w'];

		// Flowing mode
		if($y===null)
		{
			if($this->y+$h>$this->PageBreakTrigger && !$this->InHeader && !$this->InFooter && $this->AcceptPageBreak())
			{
				// Automatic page break
				$x2 = $this->x;
				$this->AddPage($this->CurOrientation,$this->CurPageSize);
				$this->x = $x2;
			}
			$y = $this->y;
			$this->y += $h;
		}

		if($x===null)
			$x = $this->x;
		$this->_out(sprintf('q %.2F 0 0 %.2F %.2F %.2F cm /I%d Do Q',$w*$this->k,$h*$this->k,$x*$this->k,($this->h-($y+$h))*$this->k,$info['i']));
		if($link)
			$this->Link($x,$y,$w,$h,$link);
	}

	function GetX()
	{
		// Get x position
		return $this->x;
	}

	function SetX($x)
	{
		// Set x position
		if($x>=0)
			$this->x = $x;
		else
			$this->x = $this->w+$x;
	}

	function GetY()
	{
		// Get y position
		return $this->y;
	}

	function SetY($y)
	{
		// Set y position and reset x
		$this->x = $this->lMargin;
		if($y>=0)
			$this->y = $y;
		else
			$this->y = $this->h+$y;
	}

	function SetXY($x, $y)
	{
		// Set x and y positions
		$this->SetY($y);
		$this->SetX($x);
	}

	function Output($name='', $dest='')
	{
		// Output PDF to some destination
		if($this->state<3)
			$this->Close();
		$dest = strtoupper($dest);
		if($dest=='')
		{
			if($name=='')
			{
				$name = 'doc.pdf';
				$dest = 'I';
			}
			else
				$dest = 'F';
		}
		switch($dest)
		{
			case 'I':
				// Send to standard output
				$this->_checkoutput();
				if(PHP_SAPI!='cli')
				{
					// We send to a browser
					header('Content-Type: application/pdf');
					header('Content-Disposition: inline; filename="'.$name.'"');
					header('Cache-Control: private, max-age=0, must-revalidate');
					header('Pragma: public');
				}
				echo $this->buffer;
				break;
			case 'D':
				// Download file
				$this->_checkoutput();
				header('Content-Type: application/x-download');
				header('Content-Disposition: attachment; filename="'.$name.'"');
				header('Cache-Control: private, max-age=0, must-revalidate');
				header('Pragma: public');
				echo $this->buffer;
				break;
			case 'F':
				// Save to local file
				$f = fopen($name,'wb');
				if(!$f)
					$this->Error('Unable to create output file: '.$name);
				fwrite($f,$this->buffer,strlen($this->buffer));
				fclose($f);
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
	*                                                                              *
	*                              Protected methods                               *
	*                                                                              *
	*******************************************************************************/
	function _dochecks()
	{
		// Check availability of %F
		if(sprintf('%.1F',1.0)!='1.0')
			$this->Error('This version of PHP is not supported');
		// Check availability of mbstring
		if(!function_exists('mb_strlen'))
			$this->Error('mbstring extension is not available');
		// Check mbstring overloading
		if(ini_get('mbstring.func_overload') & 2)
			$this->Error('mbstring overloading must be disabled');
		// Ensure runtime magic quotes are disabled
		//if(get_magic_quotes_runtime())
		//	@set_magic_quotes_runtime(0);
	}

	function _getfontpath()
	{
		return $this->fontpath;
	}

	function _checkoutput()
	{
		if(PHP_SAPI!='cli')
		{
			if(headers_sent($file,$line))
				$this->Error("Some data has already been output, can't send PDF file (output started at $file:$line)");
		}
		if(ob_get_length())
		{
			// The output buffer is not empty
			if(preg_match('/^(\xEF\xBB\xBF)?\s*$/',ob_get_contents()))
			{
				// It contains only a UTF-8 BOM and/or whitespace, let's clean it
				ob_clean();
			}
			else
				$this->Error("Some data has already been output, can't send PDF file");
		}
	}

	function _getpagesize($size)
	{
		if(is_string($size))
		{
			$size = strtolower($size);
			if(!isset($this->StdPageSizes[$size]))
				$this->Error('Unknown page size: '.$size);
			$a = $this->StdPageSizes[$size];
			return array($a[0]/$this->k, $a[1]/$this->k);
		}
		else
		{
			if($size[0]>$size[1])
				return array($size[1], $size[0]);
			else
				return $size;
		}
	}

	function _beginpage($orientation, $size)
	{
		$this->page++;
		$this->pages[$this->page] = '';
		$this->state = 2;
		$this->x = $this->lMargin;
		$this->y = $this->tMargin;
		$this->FontFamily = '';
		// Check page size and orientation
		if($orientation=='')
			$orientation = $this->DefOrientation;
		else
			$orientation = strtoupper($orientation[0]);
		if($size=='')
			$size = $this->DefPageSize;
		else
			$size = $this->_getpagesize($size);
		if($orientation!=$this->CurOrientation || $size[0]!=$this->CurPageSize[0] || $size[1]!=$this->CurPageSize[1])
		{
			// New size or orientation
			if($orientation=='P')
			{
				$this->w = $size[0];
				$this->h = $size[1];
			}
			else
			{
				$this->w = $size[1];
				$this->h = $size[0];
			}
			$this->wPt = $this->w*$this->k;
			$this->hPt = $this->h*$this->k;
			$this->PageBreakTrigger = $this->h-$this->bMargin;
			$this->CurOrientation = $orientation;
			$this->CurPageSize = $size;
		}
		if($orientation!=$this->DefOrientation || $size[0]!=$this->DefPageSize[0] || $size[1]!=$this->DefPageSize[1])
			$this->PageSizes[$this->page] = array($this->wPt, $this->hPt);
	}

	function _endpage()
	{
		$this->state = 1;
	}

	function _loadfont($font)
	{
		// Load a font definition file from the font directory
		include($this->fontpath.$font);
		$a = get_defined_vars();
		if(!isset($a['name']))
			$this->Error('Could not include font definition file');
		return $a;
	}

	function _escape($s)
	{
		// Escape special characters in strings
		$s = str_replace('\\','\\\\',$s);
		$s = str_replace('(','\\(',$s);
		$s = str_replace(')','\\)',$s);
		$s = str_replace("\r",'\\r',$s);
		return $s;
	}

	function _textstring($s)
	{
		// Format a text string
		return '('.$this->_escape($s).')';
	}

	function _UTF8toUTF16($s)
	{
		// Convert UTF-8 to UTF-16BE with BOM
		$res = "\xFE\xFF";
		$nb = strlen($s);
		$i = 0;
		while($i<$nb)
		{
			$c1 = ord($s[$i++]);
			if($c1>=224)
			{
				// 3-byte character
				$c2 = ord($s[$i++]);
				$c3 = ord($s[$i++]);
				$res .= chr((($c1 & 0x0F)<<4) + (($c2 & 0x3C)>>2));
				$res .= chr((($c2 & 0x03)<<6) + ($c3 & 0x3F));
			}
			elseif($c1>=192)
			{
				// 2-byte character
				$c2 = ord($s[$i++]);
				$res .= chr(($c1 & 0x1C)>>2);
				$res .= chr((($c1 & 0x03)<<6) + ($c2 & 0x3F));
			}
			else
			{
				// Single-byte character
				$res .= "\0".chr($c1);
			}
		}
		return $res;
	}

	function _dounderline($x, $y, $txt)
	{
		// Underline text
		$up = $this->CurrentFont['up'];
		$ut = $this->CurrentFont['ut'];
		$w = $this->GetStringWidth($txt)+$this->ws*substr_count($txt,' ');
		return sprintf('%.2F %.2F %.2F %.2F re f',$x*$this->k,($this->h-($y-$up/1000*$this->FontSize))*$this->k,$w*$this->k,-$ut/1000*$this->FontSizePt);
	}

	function _parsejpg($file)
	{
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

	function _parsepng($file)
	{
		// Extract info from a PNG file
		$f = fopen($file,'rb');
		if(!$f)
			$this->Error('Can\'t open image file: '.$file);
		$info = $this->_parsepngstream($f,$file);
		fclose($f);
		return $info;
	}

	function _parsepngstream($f, $file)
	{
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
			if($type=='PLTE')
			{
				// Read palette
				$pal = $this->_readstream($f,$n);
				$this->_readstream($f,4);
			}
			elseif($type=='tRNS')
			{
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
			elseif($type=='IDAT')
			{
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
		if($ct>=4)
		{
			// Extract alpha channel
			if(!function_exists('gzuncompress'))
				$this->Error('Zlib not available, can\'t handle alpha channel: '.$file);
			$data = gzuncompress($data);
			$color = '';
			$alpha = '';
			if($ct==4)
			{
				// Gray image
				$len = 2*$w;
				for($i=0;$i<$h;$i++)
				{
					$pos = (1+$len)*$i;
					$color .= $data[$pos];
					$alpha .= $data[$pos];
					$line = substr($data,$pos+1,$len);
					$color .= preg_replace('/(.)./s','$1',$line);
					$alpha .= preg_replace('/.(.)/s','$1',$line);
				}
			}
			else
			{
				// RGB image
				$len = 4*$w;
				for($i=0;$i<$h;$i++)
				{
					$pos = (1+$len)*$i;
					$color .= $data[$pos];
					$alpha .= $data[$pos];
					$line = substr($data,$pos+1,$len);
					$color .= preg_replace('/(.{3})./s','$1',$line);
					$alpha .= preg_replace('/.{3}(.)/s','$1',$line);
				}
			}
			unset($data);
			$data = ($this->compress) ? gzcompress($color) : $color;
			$info['smask'] = ($this->compress) ? gzcompress($alpha) : $alpha;
			if($this->PDFVersion<'1.4')
				$this->PDFVersion = '1.4';
		}
		$info['data'] = $data;
		return $info;
	}

	function _readstream($f, $n)
	{
		// Read n bytes from stream
		$res = '';
		while($n>0 && !feof($f))
		{
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

	function _readint($f)
	{
		// Read a 4-byte integer from stream
		$a = unpack('Ni',$this->_readstream($f,4));
		return $a['i'];
	}

	function _parsebmp($file)
	{
		if(!function_exists('imagepng')) return;
		$im = PDF::ImageCreateFromBMP($file);
		if(!$im)
			$this->Error('Missing or incorrect image file: '.$file);
		imageinterlace($im,0);
		$f = @fopen('php://temp','rb+');
		if($f)
		{
			// Perform conversion in memory
			ob_start();
			imagepng($im);
			$data = ob_get_clean();
			imagedestroy($im);
			fwrite($f,$data);
			rewind($f);
			$info = $this->_parsepngstream($f,$file);
			fclose($f);
		}
		else
		{
			// Use temporary file
			$tmp = tempnam('.','bmp');
			if(!$tmp)
				$this->Error('Unable to create a temporary file');
			if(!imagepng($im,$tmp))
				$this->Error('Error while saving to temporary file');
			imagedestroy($im);
			$info = $this->_parsepng($tmp);
			unlink($tmp);
		}
		return $info;
	}

	function _parsegif($file)
	{
		// Extract info from a GIF file (via PNG conversion)
		if(!function_exists('imagepng'))
			$this->Error('GD extension is required for GIF support');
		if(!function_exists('imagecreatefromgif'))
			$this->Error('GD has no GIF read support');
		$im = imagecreatefromgif($file);
		if(!$im)
			$this->Error('Missing or incorrect image file: '.$file);
		imageinterlace($im,0);
		$f = @fopen('php://temp','rb+');
		if($f)
		{
			// Perform conversion in memory
			ob_start();
			imagepng($im);
			$data = ob_get_clean();
			imagedestroy($im);
			fwrite($f,$data);
			rewind($f);
			$info = $this->_parsepngstream($f,$file);
			fclose($f);
		}
		else
		{
			// Use temporary file
			$tmp = tempnam('.','gif');
			if(!$tmp)
				$this->Error('Unable to create a temporary file');
			if(!imagepng($im,$tmp))
				$this->Error('Error while saving to temporary file');
			imagedestroy($im);
			$info = $this->_parsepng($tmp);
			unlink($tmp);
		}
		return $info;
	}

	function _newobj()
	{
		// Begin a new object
		$this->n++;
		$this->offsets[$this->n] = strlen($this->buffer);
		$this->_out($this->n.' 0 obj');
	}

	function _putstream($s)
	{
		$this->_out('stream');
		$this->_out($s);
		$this->_out('endstream');
	}

	function _out($s)
	{
		// Add a line to the document
		if($this->state==2)
			$this->pages[$this->page] .= $s."\n";
		else
			$this->buffer .= $s."\n";
	}

	function _putpages()
	{
		$nb = $this->page;
		if(!empty($this->AliasNbPages))
		{
			// Replace number of pages in fonts using subsets
			$alias = $this->UTF8ToUTF16BE($this->AliasNbPages, false);
			$r = $this->UTF8ToUTF16BE("$nb", false);
			for($n=1;$n<=$nb;$n++)
				$this->pages[$n] = str_replace($alias,$r,$this->pages[$n]);
			// Now repeat for no pages in non-subset fonts
			for($n=1;$n<=$nb;$n++)
				$this->pages[$n] = str_replace($this->AliasNbPages,$nb,$this->pages[$n]);
		}
		if($this->DefOrientation=='P')
		{
			$wPt = $this->DefPageSize[0]*$this->k;
			$hPt = $this->DefPageSize[1]*$this->k;
		}
		else
		{
			$wPt = $this->DefPageSize[1]*$this->k;
			$hPt = $this->DefPageSize[0]*$this->k;
		}
		$filter = ($this->compress) ? '/Filter /FlateDecode ' : '';
		for($n=1;$n<=$nb;$n++)
		{
			// Page
			$this->_newobj();
			$this->_out('<</Type /Page');
			$this->_out('/Parent 1 0 R');
			if(isset($this->PageSizes[$n]))
				$this->_out(sprintf('/MediaBox [0 0 %.2F %.2F]',$this->PageSizes[$n][0],$this->PageSizes[$n][1]));
			$this->_out('/Resources 2 0 R');
			if(isset($this->PageLinks[$n]))
			{
				// Links
				$annots = '/Annots [';
				foreach($this->PageLinks[$n] as $pl)
				{
					$rect = sprintf('%.2F %.2F %.2F %.2F',$pl[0],$pl[1],$pl[0]+$pl[2],$pl[1]-$pl[3]);
					$annots .= '<</Type /Annot /Subtype /Link /Rect ['.$rect.'] /Border [0 0 0] ';
					if(is_string($pl[4]))
						$annots .= '/A <</S /URI /URI '.$this->_textstring($pl[4]).'>>>>';
					else
					{
						$l = $this->links[$pl[4]];
						$h = isset($this->PageSizes[$l[0]]) ? $this->PageSizes[$l[0]][1] : $hPt;
						$annots .= sprintf('/Dest [%d 0 R /XYZ 0 %.2F null]>>',1+2*$l[0],$h-$l[1]*$this->k);
					}
				}
				$this->_out($annots.']');
			}
			if($this->PDFVersion>'1.3')
				$this->_out('/Group <</Type /Group /S /Transparency /CS /DeviceRGB>>');
			$this->_out('/Contents '.($this->n+1).' 0 R>>');
			$this->_out('endobj');
			// Page content
			$p = ($this->compress) ? gzcompress($this->pages[$n]) : $this->pages[$n];
			$this->_newobj();
			$this->_out('<<'.$filter.'/Length '.strlen($p).'>>');
			$this->_putstream($p);
			$this->_out('endobj');
		}
		// Pages root
		$this->offsets[1] = strlen($this->buffer);
		$this->_out('1 0 obj');
		$this->_out('<</Type /Pages');
		$kids = '/Kids [';
		for($i=0;$i<$nb;$i++)
			$kids .= (3+2*$i).' 0 R ';
		$this->_out($kids.']');
		$this->_out('/Count '.$nb);
		$this->_out(sprintf('/MediaBox [0 0 %.2F %.2F]',$wPt,$hPt));
		$this->_out('>>');
		$this->_out('endobj');
	}

	function _putfonts()
	{
		$nf=$this->n;
		foreach($this->diffs as $diff)
		{
			// Encodings
			$this->_newobj();
			$this->_out('<</Type /Encoding /BaseEncoding /WinAnsiEncoding /Differences ['.$diff.']>>');
			$this->_out('endobj');
		}
		foreach($this->FontFiles as $file=>$info)
		{
		   if (!isset($info['type']) || $info['type']!='TTF') {
			// Font file embedding
			$this->_newobj();
			$this->FontFiles[$file]['n']=$this->n;
			$font='';
			$f=fopen($this->_getfontpath().$file,'rb',1);
			if(!$f)
				$this->Error('Font file not found');
			while(!feof($f))
				$font.=fread($f,8192);
			fclose($f);
			$compressed=(substr($file,-2)=='.z');
			if(!$compressed && isset($info['length2']))
			{
				$header=(ord($font[0])==128);
				if($header)
				{
					// Strip first binary header
					$font=substr($font,6);
				}
				if($header && ord($font[$info['length1']])==128)
				{
					// Strip second binary header
					$font=substr($font,0,$info['length1']).substr($font,$info['length1']+6);
				}
			}
			$this->_out('<</Length '.strlen($font));
			if($compressed)
				$this->_out('/Filter /FlateDecode');
			$this->_out('/Length1 '.$info['length1']);
			if(isset($info['length2']))
				$this->_out('/Length2 '.$info['length2'].' /Length3 0');
			$this->_out('>>');
			$this->_putstream($font);
			$this->_out('endobj');
		   }
		}
		foreach($this->fonts as $k=>$font)
		{
			// Font objects
			//$this->fonts[$k]['n']=$this->n+1;
			$type = $font['type'];
			$name = $font['name'];
			if($type=='Core')
			{
				// Standard font
				$this->fonts[$k]['n']=$this->n+1;
				$this->_newobj();
				$this->_out('<</Type /Font');
				$this->_out('/BaseFont /'.$name);
				$this->_out('/Subtype /Type1');
				if($name!='Symbol' && $name!='ZapfDingbats')
					$this->_out('/Encoding /WinAnsiEncoding');
				$this->_out('>>');
				$this->_out('endobj');
			}
			elseif($type=='Type1' || $type=='TrueType')
			{
				// Additional Type1 or TrueType font
				$this->fonts[$k]['n']=$this->n+1;
				$this->_newobj();
				$this->_out('<</Type /Font');
				$this->_out('/BaseFont /'.$name);
				$this->_out('/Subtype /'.$type);
				$this->_out('/FirstChar 32 /LastChar 255');
				$this->_out('/Widths '.($this->n+1).' 0 R');
				$this->_out('/FontDescriptor '.($this->n+2).' 0 R');
				if($font['enc'])
				{
					if(isset($font['diff']))
						$this->_out('/Encoding '.($nf+$font['diff']).' 0 R');
					else
						$this->_out('/Encoding /WinAnsiEncoding');
				}
				$this->_out('>>');
				$this->_out('endobj');
				// Widths
				$this->_newobj();
				$cw=&$font['cw'];
				$s='[';
				for($i=32;$i<=255;$i++)
					$s.=$cw[chr($i)].' ';
				$this->_out($s.']');
				$this->_out('endobj');
				// Descriptor
				$this->_newobj();
				$s='<</Type /FontDescriptor /FontName /'.$name;
				foreach($font['desc'] as $k=>$v)
					$s.=' /'.$k.' '.$v;
				$file=$font['file'];
				if($file)
					$s.=' /FontFile'.($type=='Type1' ? '' : '2').' '.$this->FontFiles[$file]['n'].' 0 R';
				$this->_out($s.'>>');
				$this->_out('endobj');
			}
			// TrueType embedded SUBSETS or FULL
			else if ($type=='TTF') {
				$this->fonts[$k]['n']=$this->n+1;
				if (!class_exists('TTFontFile', false)) { include($this->_getfontpath() .'unifont/ttfonts.php'); }
				$ttf = new TTFontFile();
				$fontname = 'MPDFAA'.'+'.$font['name'];
				$subset = $font['subset'];
				unset($subset[0]);
				$ttfontstream = $ttf->makeSubset($font['ttffile'], $subset);
				$ttfontsize = strlen($ttfontstream);
				$fontstream = ($this->compress) ? gzcompress($ttfontstream) : $ttfontstream;
				$codeToGlyph = $ttf->codeToGlyph;
				unset($codeToGlyph[0]);

				// Type0 Font
				// A composite font - a font composed of other fonts, organized hierarchically
				$this->_newobj();
				$this->_out('<</Type /Font');
				$this->_out('/Subtype /Type0');
				$this->_out('/BaseFont /'.$fontname.'');
				$this->_out('/Encoding /Identity-H'); 
				$this->_out('/DescendantFonts ['.($this->n + 1).' 0 R]');
				$this->_out('/ToUnicode '.($this->n + 2).' 0 R');
				$this->_out('>>');
				$this->_out('endobj');

				// CIDFontType2
				// A CIDFont whose glyph descriptions are based on TrueType font technology
				$this->_newobj();
				$this->_out('<</Type /Font');
				$this->_out('/Subtype /CIDFontType2');
				$this->_out('/BaseFont /'.$fontname.'');
				$this->_out('/CIDSystemInfo '.($this->n + 2).' 0 R'); 
				$this->_out('/FontDescriptor '.($this->n + 3).' 0 R');
				if (isset($font['desc']['MissingWidth'])){
					$this->_out('/DW '.$font['desc']['MissingWidth'].''); 
				}

				$this->_putTTfontwidths($font, $ttf->maxUni);

				$this->_out('/CIDToGIDMap '.($this->n + 4).' 0 R');
				$this->_out('>>');
				$this->_out('endobj');

				// ToUnicode
				$this->_newobj();
				$this->_out('<</Length 345>>');
				$this->_out('stream');
				$this->_out('/CIDInit /ProcSet findresource begin');
				$this->_out('12 dict begin');
				$this->_out('begincmap');
				$this->_out('/CIDSystemInfo');
				$this->_out('<</Registry (Adobe)');
				$this->_out('/Ordering (UCS)');
				$this->_out('/Supplement 0');
				$this->_out('>> def');
				$this->_out('/CMapName /Adobe-Identity-UCS def');
				$this->_out('/CMapType 2 def');
				$this->_out('1 begincodespacerange');
				$this->_out('<0000> <FFFF>');
				$this->_out('endcodespacerange');
				$this->_out('1 beginbfrange');
				$this->_out('<0000> <FFFF> <0000>');
				$this->_out('endbfrange');
				$this->_out('endcmap');
				$this->_out('CMapName currentdict /CMap defineresource pop');
				$this->_out('end');
				$this->_out('end');
				$this->_out('endstream');
				$this->_out('endobj');

				// CIDSystemInfo dictionary
				$this->_newobj();
				$this->_out('<</Registry (Adobe)'); 
				$this->_out('/Ordering (UCS)');
				$this->_out('/Supplement 0');
				$this->_out('>>');
				$this->_out('endobj');

				// Font descriptor
				$this->_newobj();
				$this->_out('<</Type /FontDescriptor');
				$this->_out('/FontName /'.$fontname);
				foreach($font['desc'] as $kd=>$v) {
					if ($kd == 'Flags') { $v = $v | 4; $v = $v & ~32; }	// SYMBOLIC font flag
					$this->_out(' /'.$kd.' '.$v);
				}
				$this->_out('/FontFile2 '.($this->n + 2).' 0 R');
				$this->_out('>>');
				$this->_out('endobj');

				// Embed CIDToGIDMap
				// A specification of the mapping from CIDs to glyph indices
				$cidtogidmap = '';
				$cidtogidmap = str_pad('', 256*256*2, "\x00");
				foreach($codeToGlyph as $cc=>$glyph) {
					$cidtogidmap[$cc*2] = chr($glyph >> 8);
					$cidtogidmap[$cc*2 + 1] = chr($glyph & 0xFF);
				}
				if ($this->compress) $cidtogidmap = gzcompress($cidtogidmap);
				$this->_newobj();
				$this->_out('<</Length '.strlen($cidtogidmap).'');
				$this->_out('/Filter /FlateDecode');
				$this->_out('>>');
				$this->_putstream($cidtogidmap);
				$this->_out('endobj');

				//Font file 
				$this->_newobj();
				$this->_out('<</Length '.strlen($fontstream));
				$this->_out('/Filter /FlateDecode');
				$this->_out('/Length1 '.$ttfontsize);
				$this->_out('>>');
				$this->_putstream($fontstream);
				$this->_out('endobj');
				unset($ttf);
			} 
			else
			{
				// Allow for additional types
				$this->fonts[$k]['n'] = $this->n+1;
				$mtd='_put'.strtolower($type);
				if(!method_exists($this,$mtd))
					$this->Error('Unsupported font type: '.$type);
				$this->$mtd($font);
			}
		}
	}

	function _putTTfontwidths(&$font, $maxUni) {
		if (file_exists($font['unifilename'].'.cw127.php')) {
			include($font['unifilename'].'.cw127.php') ;
			$startcid = 128;
		}
		else {
			$rangeid = 0;
			$range = array();
			$prevcid = -2;
			$prevwidth = -1;
			$interval = false;
			$startcid = 1;
		}
		$cwlen = $maxUni + 1; 

		// for each character
		for ($cid=$startcid; $cid<$cwlen; $cid++) {
			if ($cid==128 && (!file_exists($font['unifilename'].'.cw127.php'))) {
				if (is_writable(dirname($this->_getfontpath().'unifont/x'))) {
					$fh = fopen($font['unifilename'].'.cw127.php',"wb");
					$cw127='<?php'."\n";
					$cw127.='$rangeid='.$rangeid.";\n";
					$cw127.='$prevcid='.$prevcid.";\n";
					$cw127.='$prevwidth='.$prevwidth.";\n";
					if ($interval) { $cw127.='$interval=true'.";\n"; }
					else { $cw127.='$interval=false'.";\n"; }
					$cw127.='$range='.var_export($range,true).";\n";
					$cw127.="?>";
					fwrite($fh,$cw127,strlen($cw127));
					fclose($fh);
				}
			}
			if ($font['cw'][$cid*2] == "\00" && $font['cw'][$cid*2+1] == "\00") { continue; }
			$width = (ord($font['cw'][$cid*2]) << 8) + ord($font['cw'][$cid*2+1]);
			if ($width == 65535) { $width = 0; }
			if ($cid > 255 && (!isset($font['subset'][$cid]) || !$font['subset'][$cid])) { continue; }
			if (!isset($font['dw']) || (isset($font['dw']) && $width != $font['dw'])) {
				if ($cid == ($prevcid + 1)) {
					if ($width == $prevwidth) {
						if ($width == $range[$rangeid][0]) {
							$range[$rangeid][] = $width;
						}
						else {
							array_pop($range[$rangeid]);
							// new range
							$rangeid = $prevcid;
							$range[$rangeid] = array();
							$range[$rangeid][] = $prevwidth;
							$range[$rangeid][] = $width;
						}
						$interval = true;
						$range[$rangeid]['interval'] = true;
					} else {
						if ($interval) {
							// new range
							$rangeid = $cid;
							$range[$rangeid] = array();
							$range[$rangeid][] = $width;
						}
						else { $range[$rangeid][] = $width; }
						$interval = false;
					}
				} else {
					$rangeid = $cid;
					$range[$rangeid] = array();
					$range[$rangeid][] = $width;
					$interval = false;
				}
				$prevcid = $cid;
				$prevwidth = $width;
			}
		}
		$prevk = -1;
		$nextk = -1;
		$prevint = false;
		foreach ($range as $k => $ws) {
			$cws = count($ws);
			if (($k == $nextk) AND (!$prevint) AND ((!isset($ws['interval'])) OR ($cws < 4))) {
				if (isset($range[$k]['interval'])) { unset($range[$k]['interval']); }
				$range[$prevk] = array_merge($range[$prevk], $range[$k]);
				unset($range[$k]);
			}
			else { $prevk = $k; }
			$nextk = $k + $cws;
			if (isset($ws['interval'])) {
				if ($cws > 3) { $prevint = true; }
				else { $prevint = false; }
				unset($range[$k]['interval']);
				--$nextk;
			}
			else { $prevint = false; }
		}
		$w = '';
		foreach ($range as $k => $ws) {
			if (count(array_count_values($ws)) == 1) { $w .= ' '.$k.' '.($k + count($ws) - 1).' '.$ws[0]; }
			else { $w .= ' '.$k.' [ '.implode(' ', $ws).' ]' . "\n"; }
		}
		$this->_out('/W ['.$w.' ]');
	}

	function _putimages()
	{
		foreach(array_keys($this->images) as $file)
		{
			$this->_putimage($this->images[$file]);
			unset($this->images[$file]['data']);
			unset($this->images[$file]['smask']);
		}
	}

	function _putimage(&$info)
	{
		$this->_newobj();
		$info['n'] = $this->n;
		$this->_out('<</Type /XObject');
		$this->_out('/Subtype /Image');
		$this->_out('/Width '.$info['w']);
		$this->_out('/Height '.$info['h']);
		if($info['cs']=='Indexed')
			$this->_out('/ColorSpace [/Indexed /DeviceRGB '.(strlen($info['pal'])/3-1).' '.($this->n+1).' 0 R]');
		else
		{
			$this->_out('/ColorSpace /'.$info['cs']);
			if($info['cs']=='DeviceCMYK')
				$this->_out('/Decode [1 0 1 0 1 0 1 0]');
		}
		$this->_out('/BitsPerComponent '.$info['bpc']);
		if(isset($info['f']))
			$this->_out('/Filter /'.$info['f']);
		if(isset($info['dp']))
			$this->_out('/DecodeParms <<'.$info['dp'].'>>');
		if(isset($info['trns']) && is_array($info['trns']))
		{
			$trns = '';
			for($i=0;$i<count($info['trns']);$i++)
				$trns .= $info['trns'][$i].' '.$info['trns'][$i].' ';
			$this->_out('/Mask ['.$trns.']');
		}
		if(isset($info['smask']))
			$this->_out('/SMask '.($this->n+1).' 0 R');
		$this->_out('/Length '.strlen($info['data']).'>>');
		$this->_putstream($info['data']);
		$this->_out('endobj');
		// Soft mask
		if(isset($info['smask']))
		{
			$dp = '/Predictor 15 /Colors 1 /BitsPerComponent 8 /Columns '.$info['w'];
			$smask = array('w'=>$info['w'], 'h'=>$info['h'], 'cs'=>'DeviceGray', 'bpc'=>8, 'f'=>$info['f'], 'dp'=>$dp, 'data'=>$info['smask']);
			$this->_putimage($smask);
		}
		// Palette
		if($info['cs']=='Indexed')
		{
			$filter = ($this->compress) ? '/Filter /FlateDecode ' : '';
			$pal = ($this->compress) ? gzcompress($info['pal']) : $info['pal'];
			$this->_newobj();
			$this->_out('<<'.$filter.'/Length '.strlen($pal).'>>');
			$this->_putstream($pal);
			$this->_out('endobj');
		}
	}

	function _putxobjectdict()
	{
		foreach($this->images as $image)
			$this->_out('/I'.$image['i'].' '.$image['n'].' 0 R');
	}

	function _putresourcedict()
	{
		$this->_out('/ProcSet [/PDF /Text /ImageB /ImageC /ImageI]');
		$this->_out('/Font <<');
		foreach($this->fonts as $font) {
			$this->_out('/F'.$font['i'].' '.$font['n'].' 0 R');
		}
		$this->_out('>>');
		$this->_out('/XObject <<');
		$this->_putxobjectdict();
		$this->_out('>>');
	}

	function _putresources()
	{
		$this->_putfonts();
		$this->_putimages();
		// Resource dictionary
		$this->offsets[2] = strlen($this->buffer);
		$this->_out('2 0 obj');
		$this->_out('<<');
		$this->_putresourcedict();
		$this->_out('>>');
		$this->_out('endobj');
	}

	function _putinfo()
	{
		$this->_out('/Producer '.$this->_textstring('tFPDF '.tFPDF_VERSION));
		if(!empty($this->title))
			$this->_out('/Title '.$this->_textstring($this->title));
		if(!empty($this->subject))
			$this->_out('/Subject '.$this->_textstring($this->subject));
		if(!empty($this->author))
			$this->_out('/Author '.$this->_textstring($this->author));
		if(!empty($this->keywords))
			$this->_out('/Keywords '.$this->_textstring($this->keywords));
		if(!empty($this->creator))
			$this->_out('/Creator '.$this->_textstring($this->creator));
		$this->_out('/CreationDate '.$this->_textstring('D:'.@date('YmdHis')));
	}

	function _putcatalog()
	{
		$this->_out('/Type /Catalog');
		$this->_out('/Pages 1 0 R');
		if($this->ZoomMode=='fullpage')
			$this->_out('/OpenAction [3 0 R /Fit]');
		elseif($this->ZoomMode=='fullwidth')
			$this->_out('/OpenAction [3 0 R /FitH null]');
		elseif($this->ZoomMode=='real')
			$this->_out('/OpenAction [3 0 R /XYZ null null 1]');
		elseif(!is_string($this->ZoomMode))
			$this->_out('/OpenAction [3 0 R /XYZ null null '.sprintf('%.2F',$this->ZoomMode/100).']');
		if($this->LayoutMode=='single')
			$this->_out('/PageLayout /SinglePage');
		elseif($this->LayoutMode=='continuous')
			$this->_out('/PageLayout /OneColumn');
		elseif($this->LayoutMode=='two')
			$this->_out('/PageLayout /TwoColumnLeft');
	}

	function _putheader()
	{
		$this->_out('%PDF-'.$this->PDFVersion);
	}

	function _puttrailer()
	{
		$this->_out('/Size '.($this->n+1));
		$this->_out('/Root '.$this->n.' 0 R');
		$this->_out('/Info '.($this->n-1).' 0 R');
	}

	function _enddoc()
	{
		$this->_putheader();
		$this->_putpages();
		$this->_putresources();
		// Info
		$this->_newobj();
		$this->_out('<<');
		$this->_putinfo();
		$this->_out('>>');
		$this->_out('endobj');
		// Catalog
		$this->_newobj();
		$this->_out('<<');
		$this->_putcatalog();
		$this->_out('>>');
		$this->_out('endobj');
		// Cross-ref
		$o = strlen($this->buffer);
		$this->_out('xref');
		$this->_out('0 '.($this->n+1));
		$this->_out('0000000000 65535 f ');
		for($i=1;$i<=$this->n;$i++)
			$this->_out(sprintf('%010d 00000 n ',$this->offsets[$i]));
		// Trailer
		$this->_out('trailer');
		$this->_out('<<');
		$this->_puttrailer();
		$this->_out('>>');
		$this->_out('startxref');
		$this->_out($o);
		$this->_out('%%EOF');
		$this->state = 3;
	}

	// ********* NEW FUNCTIONS *********
	// Converts UTF-8 strings to UTF16-BE.
	function UTF8ToUTF16BE($str, $setbom=true) {
		$outstr = "";
		if ($setbom) {
			$outstr .= "\xFE\xFF"; // Byte Order Mark (BOM)
		}
		$outstr .= mb_convert_encoding($str, 'UTF-16BE', 'UTF-8');
		return $outstr;
	}

	// Converts UTF-8 strings to codepoints array
	function UTF8StringToArray($str) {
	   $out = array();
       $str = (string)$str;
	   $len = strlen($str);
	   for ($i = 0; $i < $len; $i++) {
          $uni = -1;
		  $h = ord($str[$i]);
		  if ( $h <= 0x7F )
			 $uni = $h;
		  elseif ( $h >= 0xC2 ) {
			 if ( ($h <= 0xDF) && ($i < $len -1) )
				$uni = ($h & 0x1F) << 6 | (ord($str[++$i]) & 0x3F);
			 elseif ( ($h <= 0xEF) && ($i < $len -2) )
				$uni = ($h & 0x0F) << 12 | (ord($str[++$i]) & 0x3F) << 6
										   | (ord($str[++$i]) & 0x3F);
			 elseif ( ($h <= 0xF4) && ($i < $len -3) )
				$uni = ($h & 0x0F) << 18 | (ord($str[++$i]) & 0x3F) << 12
										   | (ord($str[++$i]) & 0x3F) << 6
										   | (ord($str[++$i]) & 0x3F);
		  }
		if ($uni >= 0) {
			$out[] = $uni;
		}
	   }
	   return $out;
	}


	// End of class
}

// Handle special IE contype request
if(isset($_SERVER['HTTP_USER_AGENT']) && $_SERVER['HTTP_USER_AGENT']=='contype')
{
	header('Content-Type: application/pdf');
	exit;
}
