<?php

// UBICACIÓN: app/Helpers/helpers.php
//
// Este archivo contiene funciones globales que puedes usar en toda la app

if (!function_exists('formatMoney')) {
    /**
     * Formatear cantidad como moneda MXN
     */
    function formatMoney($amount)
    {
        return '$' . number_format($amount ?? 0, 2, '.', ',');
    }
}

if (!function_exists('formatDate')) {
    /**
     * Formatear fecha en español
     */
    function formatDate($date, $format = 'd/m/Y')
    {
        if (!$date) return '-';
        
        if (is_string($date)) {
            $date = \Carbon\Carbon::parse($date);
        }
        
        return $date->format($format);
    }
}

if (!function_exists('formatDateTime')) {
    /**
     * Formatear fecha y hora en español
     */
    function formatDateTime($date)
    {
        return formatDate($date, 'd/m/Y H:i');
    }
}

if (!function_exists('cleanRFC')) {
    /**
     * Limpiar RFC (mayúsculas, sin espacios)
     */
    function cleanRFC($rfc)
    {
        return strtoupper(trim(str_replace(' ', '', $rfc)));
    }
}

if (!function_exists('percentFormat')) {
    /**
     * Formatear porcentaje
     */
    function percentFormat($value, $decimals = 2)
    {
        return number_format($value, $decimals) . '%';
    }
}