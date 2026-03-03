<?php

namespace App\Service;

class UtilitiesService
{
	protected const displayNumberRegex = "/^-?(\d{1,3}|\.\d{3}){0,3}(\,\d{1,3})?$/";
	protected const emailRegex = "/^([a\.\--z\_]*[a0-z9]+@)([a-z]+\.)([a-z]{2,6})$/";
	private const charset = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
    private const special_chars = "-_.,";

    public function __construct()
    {}

    /**
     * @return bool
     */
    public static function isLocalhost(): bool {
        return strpos($_SERVER["SERVER_NAME"], "localhost") === 0;
    }

    /**
     * @return bool
     */
    public static function isDevelopment(): bool {
        return getenv('SERVER_APACHE_TYPE') != 'prod';
    }
	
	/**
	 * @param array $target
	 * @param $element
	 * @return array
	 */
    public static function array_exclude(array $target, $element): array {
        $tmp = [];

        foreach($target as $t) {
            if($t == $element) {
                continue;
            }
            $tmp[] = $t;
        }
        
        return $tmp;
    }
	
	/**
	 * @param $number
	 * @param int $precision
	 * @return float
	 */
    public static function number_format($number, int $precision): float {
        return floatval(bcadd(floatval($number), 0, $precision));
    }

    /**
     * transforms display numbers into usable numbers
     * example inputs: 
     * 		9,99 -> valid
     * 		9999 -> valid
     * 		9.999 -> valid
     * 		99.999 -> valid
     * 		999,9 -> valid
     * 		999.999.999,999 -> valid
     * 		99.99 -> invalid
     * 		999.9 -> invalid
     * 
     * @param $number
	 * @param int $precision
     * @return float
     */
    public static function display2number($number, int $precision = 3): float {
    	$number = "$number";

    	# theoretically the separator for the thousands
    	$number = str_replace(".", "", $number);
    	# theoretically the separator for the decimals
    	$number = str_replace(",", ".", $number);

    	# there must be only one comma
    	if(str_word_count($number, 0, ".") > 1) {
    		$tmp = "";
    		$ciphers = explode(".", $number);

    		for($x = 0;$x < count($ciphers) - 2;$x++) {
    			$tmp += $ciphers[$x];
    		}

    		$tmp += "." . $ciphers[count($ciphers) - 1];
    	}

    	return UtilitiesService::number_format($number, $precision);
    }

    /**
     * @param float|int $number
     * @return string
     */
    public static function number2display($number): string {
		if(is_string($number) || is_null($number)) {
			try {
				$number = floatval($number);
			} catch(Exception $ignore) {}
		}

		return number_format($number, 2, ',', '.');
	}
	
	/**
     * @param array $chars
     * @param string $target
     * @return string
     */
    public static function replace(array $chars, string $target): string {
        foreach($chars as $char => $replacement) {
            $target = str_replace($char, $replacement, $target);
        }
        
        return $target;
    }

    /**
     * @param int $length
     * @param bool $special_chars
     * @return string
     */
    public static function generateRandomString(int $length, bool $special_chars = false): string {
        $result = "";
        $charset = self::charset;
        
        if($special_chars) {
            $charset .= self::special_chars;
        }
        
        for($x = 0;$x<=$length;$x++) {
            $num = mt_rand(0, strlen($charset));
            $result .= substr($charset, $num, 1);
        }
        
        return $result;
    }

    /**
     * @param string $filename
     * @return string
     */
    public static function getFileExtension(string $filename): string {
        preg_match("/\..{1,6}$/", $filename, $matches);

        return !empty($matches) ? $matches[0] : "";
    }

    /**
     * @param string $target
     * @return string
     */
    public static function capitalize(string $target): string {
        $words = explode(" ", $target);
        $temp = [];
        
        foreach($words as $word) {
            $word = strtolower($word);
            $temp[] = ucfirst($word);
        }
        
        return implode(" ", $temp);
    }
	
	/**
	 * hides part of the string.
	 * primarily used for privacy reasons
	 *
	 * example: blur("email@mail.com") -> "e****@mail.com"
	 *
	 * @param string $target
	 * @param int $type
	 * @param string $replace
	 * @return string
	 */
	public static function blur(
		string $target, 
		int $type = 1, 
		string $replace = "*"
	): string {
		$tmp = "";
		
		switch($type) {
			# email
			case 1:
				$pieces = explode("@", $target);
				
				$user = $pieces[0];
				$domain = $pieces[1];
				
				$user = substr_replace(
					$user,
					str_repeat($replace, strlen($user)-1),
					1,
					strlen($user)
				);
				
				$tmp = "$user@$domain";
				
				break;
		}
		
		return $tmp;
	}

	/**
	 * checks the file header
	 * 
	 * @param string $tmpname
	 * @param string $filename
	 * @return bool
	 */
	public static function is_pdf(
		string $tmpname,
		string $filename
	): bool {
		if(!file_exists($tmpname)) {
	        return false;
	    }

	    $handle = fopen($tmpname, "rb");
	    if(!$handle) {
	        return false;
	    }

	    $first_line = fgets($handle, 20);
	    if(preg_match("/\r/", $first_line)) {
	    	$tmp = preg_split("/\r/", $first_line);
	    	$first_line = $tmp[0];
	    }

	    fseek($handle, max(-1024, -filesize($tmpname)), SEEK_END);
	    $last_chunk = fread($handle, 1024);

	    fclose($handle);

	    /**
	     * we expect the very first line
	     * to be the pdf version used to create the document
	     * 
	     * NOTE: not all the pdfs have the version on one line
	     * 
	     * example: %PDF-1.5
	     */
	    if(!preg_match("/^%PDF-[0-9]\.[0-9]/", trim($first_line))) {
	        return false;
	    }

	    # looks for %%EOF at the end of the file
	    if(!preg_match("/%%EOF/", $last_chunk)) {
	        return false;
	    }

	    return preg_match("/\.pdf$/i", $filename);
	}

	/**
	 * removes all the null values
	 * from the array
	 * 
	 * @param array $target
	 * @param bool $check_values
	 * @param bool $mantain_index
	 * @return array
	 */
	public static function array_clear(
		?array $target, 
		bool $check_values, 
		bool $mantain_index
	): array {
	    $temp = [];

	    if(!empty($target)) {
			foreach($target as $index => $value) {
	    		if(
	    			($check_values && !empty($value)) 
	    			|| (!$check_values && !empty($index))
	    		) {
	    			if(!$mantain_index) {
			            $temp[] = $check_values ? $value : $index;
	    			} else {
			            $temp[$index] = $check_values ? $value : $index;
	    			}
		        }
		    }
    	}

	    return $temp;
	}

	/**
	 * @param $num1
	 * @param $num2
	 * @param int $precision
	 * @return float
	 */
	public static function ftsub(
		$num1,
		$num2,
		int $precision = 3
	): float {
		$num1 = $num1 ?? 0;
		$num2 = $num2 ?? 0;

		return bcsub(strval($num1), strval($num2), $precision);
	}

	/**
	 * @param string $target
	 * @return int|false
	 */
	public static function is_email(string $target): int|false
	{
	    return preg_match(self::emailRegex, $target);
	}

	/**
	 * @param mixed $number
	 * @param int $precision
	 * @return float|int
	 */
    public static function floor($number, int $precision = 0) {
        $n = floor(floatval($number));

        if($precision) {
            $n = self::number_format($n, $precision);
        }

        return $n;
    }

	/**
	 * @param mixed $number
	 * @param int $precision
	 * @return float|int
	 */
    public static function ceil($number, int $precision = 0) {
        $n = ceil(floatval($number));

        if($precision) {
            $n = self::number_format($n, $precision);
        }

        return $n;
    }

	/**
	 * @param mixed $number
	 * @param int $precision
	 * @return float|int
	 */
    public static function round($number, int $precision = 0) {
        $n = round(floatval($number));

        if($precision) {
            $n = self::number_format($n, $precision);
        }

        return $n;
    }

	/**
	 * @param array $array
	 * @return bool
	 */
	public static function array_unique(array $array): bool {
        return count(array_unique($array)) === 1;
    }

	/**
	 * @param mixed $target
	 * @param int|float $min
	 * @param int|float $max
	 * @return bool
	 */
    public static function in_range($target, $min, $max): bool {
        $target = floatval($target);
        return $target >= floatval($min) && $target <= floatval($max);
    }

	/**
	 * @param mixed $number
	 * @param int $precision
	 * @return int
	 */
    public static function truncate_num($number, int $precision): int
    {
        $length = strlen(strval($number)) - $precision;
        $length = $length < 0 ? 0 : $length;
        return intval(
            substr(
                $number, 
                $length,
                strlen(strval($number))
            )
        );
    }
	public static function array_trim(array $target) {
        return array_filter($target, function($item) {
            if(!empty($item)) {
                return $item;
            }
        });
    }

    /**
     * per risolvere
     * 
     * $target instanceof stdClass = false
     * quando $target è stdClass
     * 
     * @param mixed $target
     * @return bool
     */
    public static function is_stdClass($target): bool {
        return !@empty($target) && get_class($target) === "stdClass";
    }
}
