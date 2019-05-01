<?php
/**
 * CConvert is a helper class that provides a set of helper methods for different type of conversions.
 *
 *
 * PUBLIC (static):			PROTECTED:					PRIVATE:
 * ----------               ----------                  ----------
 * fileSize
 */
class Convert
{
    /**
     * Converts a given size into mb Mb or Kb.
     *
     * @param int   $size
     * @param array $params
     *
     * @return float
     */
    public static function fileSize($size, $params = [])
    {
        $spaceBeforeUnit = isset($params['spaceBeforeUnit']) ? (bool) $params['spaceBeforeUnit'] : true;
        $unitCase = isset($params['unitCase']) ? $params['unitCase'] : '';
        $unit = ['b', 'kb', 'mb', 'gb', 'tb', 'pb'];

        if ($unitCase == 'camel') {
            $unit = ['b', 'Kb', 'Mb', 'Gb', 'Tb', 'Pb'];
        } elseif ($unitCase == 'upper') {
            $unit = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        }

        return @round($size / pow(1024, ($i = floor(log($size, 1024)))), 2).($spaceBeforeUnit ? ' ' : '').$unit[$i];
    }
}
