<?php

/**
 * TemplateHelper.php
 *
 * Part of the Trait-Libraray for IP-Symcon Modules.
 *
 * @package       traits
 * @author        Heiko Wilknitz <heiko@wilkware.de>
 * @copyright     2025 Heiko Wilknitz
 * @link          https://wilkware.de
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 */

declare(strict_types=1);

/**
 * Helper class for manage templates and presentations.
 */
trait TemplateHelper
{
    /**
     * Recursively translate all 'Caption' values in an array.
     * Handles arrays and JSON strings like in 'OPTIONS'.
     *
     * @param array<string,mixed> $array The input array (e.g., from the constant)
     * @return array<string,mixed> Translated array
     */
    protected function TranslateCaptions(array $array): array
    {
        foreach ($array as $key => $value) {

            // If the key is 'Caption', translate it
            if ($key === 'Caption' && is_string($value)) {
                $array[$key] = $this->Translate($value);
                continue;
            }

            // If value is an array, recurse
            if (is_array($value)) {
                $array[$key] = $this->translateCaptions($value);
            }

            // If value is a JSON string representing an array (like 'OPTIONS')
            elseif (is_string($value)) {
                $json = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                    // Recursively translate captions in decoded array, then re-encode
                    $array[$key] = json_encode($this->translateCaptions($json), JSON_UNESCAPED_UNICODE);
                }
            }
        }

        return $array;
    }
}