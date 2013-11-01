<?php
/**
 *
 *
 * @see         http://php.net/manual/class.iterator.php#96691
 *
 * @author      Knut Kohl <github@knutkohl.de>
 * @copyright   2012-2013 Knut Kohl
 * @license     GNU General Public License http://www.gnu.org/licenses/gpl.txt
 * @version     $Id: v1.0.0.2-27-gf2cf3da 2013-05-06 15:24:30 +0200 Knut Kohl $
 */
class Buffer implements Iterator, Countable {

	/**
	 * Use PHPs internal temp stream, use file for data greater 5 MB
	 */
	public function __construct( $size=5 ) {
		$this->fh = fopen('php://temp/maxmemory:'.(1024 * 1024 * $size), 'w+');
		$this->rowCount = 0;
		$this->rewind();
	}

	/**
	 *
	 */
	public function __destruct() {
	    // Not yet closed
		if (is_resource($this->fh)) fclose($this->fh);
	}

	/**
	 * Iterator
	 */
	public function rewind() {
		rewind($this->fh);
		return $this->next();
	}

	/**
	 * Iterator
	 */
	public function valid() {
		return !empty($this->data);
	}

	/**
	 * Iterator
	 */
	public function key() {
		return $this->id;
	}

	/**
	 * Iterator
	 */
	public function current() {
		return $this->data;
	}

	/**
	 * Iterator
	 */
	public function next() {
		$this->id   = NULL;
		$this->data = array();

		$data = fgets($this->fh);

		if ($data !== FALSE) {
			$data = trim($data);

			list($this->id, $keys, $values) = explode(self::SEP1, $data);

			$keys   = explode(self::SEP2, $keys);

			// Restore newlines
			$values = str_replace(self::NL, PHP_EOL, $values);
			$values = explode(self::SEP2, $values);

			$this->data = array_combine($keys, $values);
		}

		return $this;
	}

	/**
	 *
	 */
	public function write( Array $data, $id=NULL ) {

		// Skip empty data sets
		if (empty($data)) return 0;

		$this->rowCount++;

		$keys   = implode(self::SEP2, array_keys($data));
		$values = implode(self::SEP2, array_values($data));
		// Mask newlines
		$values = str_replace(PHP_EOL, self::NL, $values);

		$encoded = $id . self::SEP1 . $keys . self::SEP1 . $values;

		return fwrite($this->fh, $encoded . PHP_EOL);
	}

	/**
	 * Countable
	 */
	public function count() {
		return $this->rowCount;
	}

	/**
	 *
	 */
	public function size() {
		// Save actual position
		$pos = ftell($this->fh);

		fseek($this->fh, 0, SEEK_END);
		$size = ftell($this->fh);

		// Restore position
		fseek($this->fh, $pos);

		return $size;
	}

	/**
	 *
	 */
	public function append( Buffer $buffer ) {
        foreach ($buffer as $id=>$row) {
			$this->write($row, $id);
        }
		return $this;
	}

	/**
	 *
	 */
	public function close() {
		fclose($this->fh);
		unset($this);
	}

	// -----------------------------------------------------------------------
	// PROTECTED
	// -----------------------------------------------------------------------

	/**
	 * Separators for encoding/decoding row data
	 */
	const SEP1 = "\x01";
	const SEP2 = "\x02";
	const NL   = "\x03";

	/**
	 *
	 */
	protected $fh;

	/**
	 *
	 */
	protected $id;

	/**
	 *
	 */
	protected $data;

	/**
	 *
	 */
	protected $rowCount;

}