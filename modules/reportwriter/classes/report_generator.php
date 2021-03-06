<?php
// +-----------------------------------------------------------------+
// |                   PhreeBooks Open Source ERP                    |
// +-----------------------------------------------------------------+
// | Copyright (c) 2008, 2009, 2010 PhreeSoft, LLC                   |
// | http://www.PhreeSoft.com                                        |
// +-----------------------------------------------------------------+
// | This program is free software: you can redistribute it and/or   |
// | modify it under the terms of the GNU General Public License as  |
// | published by the Free Software Foundation, either version 3 of  |
// | the License, or any later version.                              |
// |                                                                 |
// | This program is distributed in the hope that it will be useful, |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of  |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the   |
// | GNU General Public License for more details.                    |
// |                                                                 |
// | The license that is bundled with this package is located in the |
// | file: /doc/manual/ch01-Introduction/license.html.               |
// | If not, see http://www.gnu.org/licenses/                        |
// +-----------------------------------------------------------------+
//  Path: /modules/reportwriter/classes/report_generator.php
//

if (PDF_APP == 'FPDF') { 
  require_once (DIR_FS_ADDONS . 'fpdf/fpdf.php'); // FPDF class to generate reports
} else {
  define ("K_PATH_MAIN", DIR_FS_ADDONS . "tcpdf/");
  define ("K_PATH_URL",  DIR_WS_FULL_PATH . "includes/addons/tcpdf/");
  require_once (DIR_FS_ADDONS . 'tcpdf/tcpdf.php'); // TCPDF class to generate reports, default
}
define('RowSpace', 2); // define separation between the heading rows


class PDF extends TCPDF {

	var $y0; // current y position
	var $x0; // current x position
	var $pageY; // y value of bottom of page less bottom margin

	function PDF() {
		global $Prefs;
		$PaperSize = explode(':', $Prefs['papersize']);
		if (PDF_APP == 'FPDF') {
			$this->FPDF($Prefs['paperorientation'], 'mm', $PaperSize[0]);
		} else {
			parent::__construct($Prefs['paperorientation'], 'mm', $PaperSize[0], true, 'UTF-8', false); 
			$this->SetCellPadding(0);
		}
		if ($Prefs['paperorientation'] == 'P') { // Portrait - calculate max page height
			$this->pageY = $PaperSize[2] - $Prefs['marginbottom'];
		} else { // Landscape
			$this->pageY = $PaperSize[1] - $Prefs['marginbottom'];
		}
		// fetch the column widths and put into array to match the columns of data
		$CellXPos[0] = $Prefs['marginleft'];
		$col = 1;
		foreach ($Prefs['FieldListings'] as $field) {
		  if ($field['visible']) {
			if (isset($field['params']['columnwidth'])) {
				$CellXPos[$col] = max($CellXPos[$col], $CellXPos[$col-1] + $field['params']['columnwidth']);
			} else if (isset($Prefs['col' . $col . 'width'])) { // backward compatibility
				$CellXPos[$col] = $CellXPos[$col-1] + $Prefs['col' . $col . 'width'];
			} else {
				$CellXPos[$col] = $CellXPos[$col-1] + RW_DEFAULT_COLUMN_WIDTH;
			}
			if ($field['columnbreak']) $col++;
		  }
		}
		$this->columnWidths = $CellXPos;

		$this->SetMargins($Prefs['marginleft'], $Prefs['margintop'], $Prefs['marginright']);
		$this->SetAutoPageBreak(0, $Prefs['marginbottom']);
		$this->SetFont($Prefs['coynamefont']);
		$this->SetDrawColor(128, 0, 0);
		$this->SetLineWidth(0.35); // 1 point
		$this->AliasNbPages();
		$this->AddPage();
	}

	function Header() {
		global $Prefs, $Heading, $Seq;
		$this->SetX($Prefs['marginleft']);
		$this->SetY($Prefs['margintop']);
		$this->SetFillColor(255);
		if ($Prefs['coynameshow']) { // Show the company name
			$this->SetFont($Prefs['coynamefont'], 'B', $Prefs['coynamefontsize']);
			$Colors = explode(':', $Prefs['coynamefontcolor']);
			$this->SetTextColor($Colors[0], $Colors[1], $Colors[2]);
			$CellHeight = ($Prefs['coynamefontsize'] + RowSpace) * 0.35;
			$this->Cell(0, $CellHeight, COMPANY_NAME, 0, 1, $Prefs['coynamealign']);
		}
		if ($Prefs['title1show']) { // Set title 1 heading
			$this->SetFont($Prefs['title1font'], '', $Prefs['title1fontsize']);
			$Colors = explode(':', $Prefs['title1fontcolor']);
			$this->SetTextColor($Colors[0], $Colors[1], $Colors[2]);
			$CellHeight = ($Prefs['title1fontsize'] + RowSpace) * 0.35;
			$this->Cell(0, $CellHeight, TextReplace($Prefs['title1desc']), 0, 1, $Prefs['title1fontalign']);
		}
		if ($Prefs['title2show']) { // Set Title 2 heading
			$this->SetFont($Prefs['title2font'], '', $Prefs['title2fontsize']);
			$Colors = explode(':', $Prefs['title2fontcolor']);
			$this->SetTextColor($Colors[0], $Colors[1], $Colors[2]);
			$CellHeight = ($Prefs['title2fontsize'] + RowSpace) * 0.35;
			$this->Cell(0, $CellHeight, TextReplace($Prefs['title2desc']), 0, 1, $Prefs['title2fontalign']);
		}
		// Set the filter heading
		$this->SetFont($Prefs['filterfont'], '', $Prefs['filterfontsize']);
		$Colors = explode(':', $Prefs['filterfontcolor']);
		$this->SetTextColor($Colors[0], $Colors[1], $Colors[2]);
		$CellHeight = ($Prefs['filterfontsize'] + RowSpace) * 0.35; // convert points to mm
		$this->MultiCell(0, $CellHeight, $Prefs['filterdesc'], 'B', 1, $Prefs['filterfontalign']);
		$this->y0 = $this->GetY(); // set y position after report headings before column titles
		// Set the table header
		$this->SetFont($Prefs['datafont'], '', $Prefs['datafontsize']);
		$Colors = explode(':', $Prefs['datafontcolor']);
		$this->SetTextColor($Colors[0], $Colors[1], $Colors[2]);
		$this->SetDrawColor(128, 0, 0);
		$this->SetLineWidth(.35); // 1 point
		$CellHeight = ($Prefs['datafontsize'] + RowSpace) * 0.35;
		// fetch the column widths
		$CellXPos = $this->columnWidths;
		// Fetch the column break array
		foreach ($Seq as $Temp) $ColBreak[] = ($Temp['break']) ? true : false;
		// See if we need to truncate the data
		$trunc = ($Prefs['trunclong'] == '1') ? true : false;
		// Ready to draw the column titles in the header
		$maxY = $this->y0; // set to track the tallest column
		$col = 1;
		$LastY = $this->y0;
		foreach ($Heading as $key => $value) {
			$this->SetLeftMargin($CellXPos[$col-1]);
			$this->SetX($CellXPos[$col-1]);
			$this->SetY($LastY);
			// truncate data if selected
			if ($trunc) $value = $this->TruncData($value, $CellXPos[$col] - $CellXPos[$col-1]);
			$this->MultiCell($CellXPos[$col]-$CellXPos[$col-1], $CellHeight, $value, 0, $Prefs['datafontalign']);
			if ($ColBreak[$key]) { 
				$col++;
				$LastY = $this->y0;
			} else $LastY = $this->GetY();
			if ($this->GetY() > $maxY) $maxY = $this->GetY(); // check for new col max height
		}
		// Draw a bottom line for the end of the heading
		$this->SetLeftMargin($CellXPos[0]);
		$this->SetX($CellXPos[0]);
		$this->SetY($this->y0);
		$this->Cell(0, $maxY-$this->y0, ' ', 'B');
		$this->y0 = $maxY + 0.35;
	}

	function Footer() {
		//Position at 1.5 cm from bottom
		$this->SetY(-8);
		//Arial italic 8
		$this->SetFont(PDF_DEFAULT_FONT, '', '8');	// fixed footer font type and size for page numbers
		$this->SetTextColor(0);
		//Page number
		$this->Cell(0, 10, TEXT_PAGE . ' ' . $this->PageNo() . ' / {nb}', 0, 0, 'C');
	}

	function ReportTable($Data) {
		global $Prefs, $Seq;
		$FillColor  = array(224, 235, 255);
		$this->SetFont($Prefs['datafont'], '', $Prefs['datafontsize']);
		$this->SetFillColor($FillColor[0], $FillColor[1], $FillColor[2]);
		$Colors     = explode(':', $Prefs['datafontcolor']);
		$this->SetTextColor($Colors[0], $Colors[1], $Colors[2]);
		$CellHeight = ($Prefs['datafontsize'] + RowSpace) * 0.35;
		// fetch the column widths
		$CellXPos   = $this->columnWidths;
		// Fetch the column break array and alignment array
		foreach ($Seq as $Temp) {
			$ColBreak[] = ($Temp['break']) ? true : false;
			$align[]    = $Temp['align'];
		}
		// See if we need to truncate the data
		$trunc          = $Prefs['trunclong'] == '1' ? true: false;
		// Ready to draw the column data
		$fill           = false;
		$NeedTop        = 'No';
		$this->MaxRowHt = 0; //track the tallest row to estimate page breaks
		$group_break    = false;
		foreach ($Data as $myrow) {
			$Action = array_shift($myrow);
			$todo = explode(':', $Action); // contains a letter of the date type and title/groupname
			switch ($todo[0]) {
				case "h": // Heading
					$this->SetLeftMargin($CellXPos[0]);
					$this->SetX($CellXPos[0]);
					$this->SetY($this->y0);
					$this->Cell(0, $CellHeight, $todo[1], 1, 1, 'L');
					$this->y0 = $this->GetY() + 0.35;
					$NeedTop = 'Next';
					$fill = false;
					break;
				case "r": // Report Total
				case "g": // Group Total
					// Draw a fill box
					if ($this->y0 + (2 * $this->MaxRowHt) > $this->pageY) $this->forcePageBreak($CellXPos[0]);
					$this->SetLeftMargin($CellXPos[0]);
					$this->SetX($CellXPos[0]);
					$this->SetY($this->y0);
					$this->SetFillColor(240);
					$this->Cell(0, $this->pageY-$this->y0, '', $brdr, 0, 'L', 1);
					// Add total heading
					$this->SetLeftMargin($CellXPos[0]);
					$this->SetX($CellXPos[0]);
					$this->SetY($this->y0);
					$Desc  = ($todo[0] == 'g') ? RW_TEXT_GROUP_TOTAL_FOR : RW_TEXT_REPORT_TOTAL_FOR;
					$this->Cell(0, $CellHeight, $Desc . $todo[1], 1, 1, 'C');
					$this->y0 = $this->GetY() + 0.35;
					$NeedTop = 'Next';
					$fill = false; // set so totals data will not be filled
					if ($todo[0] == 'g' && $Prefs['grpbreak']) $group_break = true;
					// now fall into the 'd' case to show the data
				case "d": // data element
				default:
					// figure out if a border needs to be drawn for total separation 
					// and fill color (draws an empty box over the row just written with the fill color)
					$brdr = 0;
					if ($NeedTop == 'Yes') {
						$brdr = 'T'; 
						$fill = false; // set so first data after total will not be filled
						$NeedTop = 'No';
					} elseif ($NeedTop == 'Next') {
						$brdr = 'LR'; 
						$NeedTop = 'Yes';
					}
					// Draw a fill box
					if (($this->y0 + $this->MaxRowHt) > $this->pageY) $this->forcePageBreak($CellXPos[0]);
					$this->SetLeftMargin($CellXPos[0]);
					$this->SetX($CellXPos[0]);
					$this->SetY($this->y0);
					if ($fill) $this->SetFillColor($FillColor[0], $FillColor[1], $FillColor[2]); else $this->SetFillColor(255);
					$this->Cell(0, $this->pageY-$this->y0, '', $brdr, 0, 'L', 1);
					// fill in the data
					$maxY  = $this->y0; // set to current top of row
					$col   = 1;
					$LastY = $this->y0;
					foreach ($myrow as $key => $value) {
						$this->SetLeftMargin($CellXPos[$col-1]);
						$this->SetX($CellXPos[$col-1]);
						$this->SetY($LastY);
						// truncate data if necessary
						if ($trunc) $value = $this->TruncData($value, $CellXPos[$col] - $CellXPos[$col-1]);
						$this->MultiCell($CellXPos[$col] - $CellXPos[$col-1], $CellHeight, $value, 0, $align[$key]);
						if ($ColBreak[$key]) { 
							$col++;
							$LastY = $this->y0;
						} else { 
							$LastY = $this->GetY();
						}
						if ($this->GetY() > $maxY) $maxY = $this->GetY();
					}
					$this->SetLeftMargin($CellXPos[0]); // restore left margin
					break;
			}
			$ThisRowHt = $maxY - $this->y0; // seee how tall this row was
			if ($ThisRowHt > $this->MaxRowHt) $this->MaxRowHt = $ThisRowHt; // keep that largest row so far to track pagination
			$this->y0 = $maxY; // set y position to largest value for next row
			$fill = !$fill;
			if ($group_break) {
				$this->forcePageBreak($CellXPos[0]);
				$group_break = false;
				continue;
			}
		}
		// Fill the end of the report with white space, temp increase margins to eliminate occasional edging problem
		$this->SetMargins($Prefs['marginleft']-0.25, $Prefs['margintop'], $Prefs['marginright']-0.25);
		$this->SetX($Prefs['marginleft']-0.25);
		$this->SetY($this->y0);
		$this->SetFillColor(255);
		$this->Cell(0, $this->pageY - $this->y0 + 0.25, '', 'T', 0, 'L', 1);
		// restore the margins 
		$this->SetMargins($Prefs['marginleft'], $Prefs['margintop'], $Prefs['marginright']);
		$this->SetLeftMargin($CellXPos[0]);
		$this->SetX($CellXPos[0]);
	}

	function forcePageBreak($CellXPos) {
		global $Prefs;
		// Fill the end of the report with white space
		$this->SetMargins($Prefs['marginleft']-0.25, $Prefs['margintop'], $Prefs['marginright']-0.25);
		$this->SetX($Prefs['marginleft']-0.25);
		$this->SetY($this->y0);
		$this->SetFillColor(255);
		$this->Cell(0, $this->pageY - $this->y0 + 0.25, '', 'T', 0, 'L', 1);
		$this->AddPage();
		$this->MaxRowHt = 0;
		$this->SetMargins($Prefs['marginleft'], $Prefs['margintop'], $Prefs['marginright']);
		$this->SetX($CellXPos);
	}

	function TruncData($strData, $ColWidth) {
		$percent = 0.90; //percent to truncate from max to account for proportional spacing
		$CurWidth = $this->GetStringWidth($strData);
		if ($CurWidth > ($ColWidth * (0.90))) { // then it needs to be truncated
			// for now we'll do an approximation based on averages and scale to 90% of the width to allow for variance
			// A better aproach would be an recursive call to this function until the string just fits.
			$NumChars = strlen($strData);
			// Reduce the string by 1-$percent and retest
			$strData = $this->TruncData(substr($strData, 0, ($ColWidth / $CurWidth) * $NumChars * $percent), $ColWidth);
		}
		return $strData;
	}

} // end class
?>