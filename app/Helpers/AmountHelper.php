<?php

if (!function_exists('amountToWords')) {

    function amountToWords($amount)
    {
        $number = floor($amount);
        $decimal = round(($amount - $number) * 100);

        $words = [
            0 => '',
            1 => 'One',
            2 => 'Two',
            3 => 'Three',
            4 => 'Four',
            5 => 'Five',
            6 => 'Six',
            7 => 'Seven',
            8 => 'Eight',
            9 => 'Nine',
            10 => 'Ten',
            11 => 'Eleven',
            12 => 'Twelve',
            13 => 'Thirteen',
            14 => 'Fourteen',
            15 => 'Fifteen',
            16 => 'Sixteen',
            17 => 'Seventeen',
            18 => 'Eighteen',
            19 => 'Nineteen',
            20 => 'Twenty',
            30 => 'Thirty',
            40 => 'Forty',
            50 => 'Fifty',
            60 => 'Sixty',
            70 => 'Seventy',
            80 => 'Eighty',
            90 => 'Ninety'
        ];

        $digits = [
            '',
            'Hundred',
            'Thousand',
            'Lakh',
            'Crore'
        ];

        $result = '';

        $getWords = function ($num) use (&$getWords, $words) {
            if ($num < 21) {
                return $words[$num];
            } elseif ($num < 100) {
                return $words[(int)($num / 10) * 10] . ' ' . $words[$num % 10];
            } elseif ($num < 1000) {
                return $words[(int)($num / 100)] . ' Hundred ' . $getWords($num % 100);
            }

            return '';
        };

        $crore = floor($number / 10000000);
        $number %= 10000000;

        $lakh = floor($number / 100000);
        $number %= 100000;

        $thousand = floor($number / 1000);
        $number %= 1000;

        $hundred = $number;

        if ($crore) {
            $result .= $getWords($crore) . ' Crore ';
        }

        if ($lakh) {
            $result .= $getWords($lakh) . ' Lakh ';
        }

        if ($thousand) {
            $result .= $getWords($thousand) . ' Thousand ';
        }

        if ($hundred) {
            $result .= $getWords($hundred);
        }

        $result = trim($result);

        if ($decimal > 0) {
            $result .= ' Rupees and ' . $getWords($decimal) . ' Paise Only';
        } else {
            $result .= ' Rupees Only';
        }

        return preg_replace('/\s+/', ' ', $result);
    }
}