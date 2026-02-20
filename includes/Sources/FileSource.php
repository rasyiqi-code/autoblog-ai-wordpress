<?php

namespace Autoblog\Sources;

use Autoblog\Interfaces\SourceInterface;
use Autoblog\Utils\Logger;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Smalot\PdfParser\Parser;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;

/**
 * Validates and fetches data from uploaded files.
 *
 * @package    Autoblog
 * @subpackage Autoblog/includes/Sources
 * @author     Rasyiqi
 */
class FileSource implements SourceInterface {

	/**
	 * The path to the file.
	 *
	 * @var string
	 */
	private $file_path;

	/**
	 * Initialize the class.
	 *
	 * @param string $file_path The absolute path to the file.
	 */
	public function __construct( $file_path ) {
		$this->file_path = $file_path;
	}

	/**
	 * Fetch data from the file source.
	 *
	 * @return array Array of raw data items.
	 */
	public function fetch_data() {
		
		$data = array();

		if ( ! $this->validate_source() ) {
			return $data;
		}

		$extension = strtolower( pathinfo( $this->file_path, PATHINFO_EXTENSION ) );

		try {
			
			switch ( $extension ) {
				case 'xlsx':
				case 'csv':
					$data = $this->parse_spreadsheet();
					break;
				case 'pdf':
					$data = $this->parse_pdf();
					break;
				case 'docx':
					$data = $this->parse_docx();
					break;
				case 'txt':
				case 'md':
					$data = $this->parse_text();
					break;
				default:
					Logger::log( "Unsupported file extension: {$extension}", 'warning' );
					break;
			}

		} catch ( \Exception $e ) {
			Logger::log( 'Error parsing file: ' . $e->getMessage(), 'error' );
		}

		return $data;

	}

	/**
	 * Parse Excel or CSV files.
	 *
	 * @return array
	 */
	private function parse_spreadsheet() {
        $reader = IOFactory::createReaderForFile( $this->file_path );
        $reader->setReadDataOnly( true ); // Load without styles to save massive RAM
		$spreadsheet = $reader->load( $this->file_path );
		$worksheet   = $spreadsheet->getActiveSheet();
        
        $items = array();
        foreach ( $worksheet->getRowIterator() as $row ) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells( true ); // Loop only populated cells

            $rowData = [];
            foreach ( $cellIterator as $cell ) {
                $rowData[] = $cell->getValue();
            }

            // Skip empty rows
            if ( empty( array_filter( $rowData ) ) ) {
                continue;
            }

            $items[] = array(
                'content'     => mb_convert_encoding( implode( ' ', $rowData ), 'UTF-8', 'UTF-8' ),
                'source_type' => 'file',
                'source_url'  => $this->file_path
            );

            // Safety limit to prevent VectorStore bloat and OOM
            if ( count( $items ) > 2500 ) {
                 Logger::log( 'VectorStore Warning: File xlsx/csv melewati 2500 baris. Truncating data to safely fit Memory limit.', 'warning' );
                 break;
            }
        }
        
        // Free RAM
        $spreadsheet->disconnectWorksheets();
        unset( $spreadsheet );

		return $items;
	}

	/**
	 * Parse PDF files.
	 *
	 * @return array
	 */
	private function parse_pdf() {
		$parser = new Parser();
		$pdf    = $parser->parseFile( $this->file_path );
		$text   = mb_convert_encoding( $pdf->getText(), 'UTF-8', 'UTF-8' );

		return array(
			array(
				'content'     => $text,
				'source_type' => 'file',
				'source_url'  => $this->file_path
			)
		);
	}

	/**
	 * Parse Word Documents.
	 *
	 * @return array
	 */
	private function parse_docx() {
		$phpWord = WordIOFactory::load( $this->file_path );
        $text = '';
        $max_length = 200000; // Limit roughly 30-50 pages of words to prevent infinite PHP allocation

        foreach ( $phpWord->getSections() as $section ) {
            foreach ( $section->getElements() as $element ) {
                if ( method_exists( $element, 'getText' ) ) {
                    $text .= $element->getText() . "\n";
                }
                // Memory Guard
                if ( strlen( $text ) > $max_length ) {
                     Logger::log( 'VectorStore Warning: DOCX melebihi limit panjang karakter. Truncating to safely fit Memory limit.', 'warning' );
                     break 2;
                }
            }
        }
        $text = mb_convert_encoding( $text, 'UTF-8', 'UTF-8' );

		return array(
			array(
				'content'     => $text,
				'source_type' => 'file',
				'source_url'  => $this->file_path
			)
		);
	}

	/**
	 * Parse Text or Markdown files.
	 *
	 * @return array
	 */
	private function parse_text() {
		$text = file_get_contents( $this->file_path );
		$text = mb_convert_encoding( $text, 'UTF-8', 'UTF-8' );

		return array(
			array(
				'content'     => $text,
				'source_type' => 'file',
				'source_url'  => $this->file_path
			)
		);
	}

	/**
	 * Validate if the source is accessible and valid.
	 *
	 * @return bool True if valid, false otherwise.
	 */
	public function validate_source() {
		
		if ( ! file_exists( $this->file_path ) ) {
			Logger::log( 'File does not exist: ' . $this->file_path, 'warning' );
			return false;
		}

		return true;
	}

	/**
	 * Get the type of the source.
	 *
	 * @return string Source type.
	 */
	public function get_display_name() {
		return 'File Upload';
	}

}
