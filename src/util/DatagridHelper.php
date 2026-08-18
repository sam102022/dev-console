<?php
declare(strict_types=1);

namespace App\util;

class DatagridHelper
{
    public static function process(array $items, array $filters, string $sortCol, string $sortDir, int $page, int $limit): array
    {
        // 1. Filtrage
        $filtered = array_filter($items, function ($item) use ($filters) {
            foreach ($filters as $key => $val) {
                if ($val === '' || $val === 'all') continue;
                $itemVal = self::getPropertyValue($item, $key);
                if (is_string($itemVal) && stripos($itemVal, (string)$val) === false) {
                    return false;
                }
            }
            return true;
        });

        // 2. Tri
        if ($sortCol !== '') {
            usort($filtered, function ($a, $b) use ($sortCol, $sortDir) {
                $valA = self::getPropertyValue($a, $sortCol);
                $valB = self::getPropertyValue($b, $sortCol);
                
                $numA = is_numeric($valA) ? (float)$valA : false;
                $numB = is_numeric($valB) ? (float)$valB : false;
                
                if ($numA !== false && $numB !== false) {
                    $cmp = $numA <=> $numB;
                } else {
                    $cmp = strcasecmp((string)$valA, (string)$valB);
                }
                
                return $sortDir === 'asc' ? $cmp : -$cmp;
            });
        } else {
             // Reset les clés pour que array_slice marche correctement si pas de tri
             $filtered = array_values($filtered);
        }

        // 3. Pagination
        $totalRows = count($filtered);
        $offset = ($page - 1) * $limit;
        
        $pagedItems = array_slice($filtered, $offset, $limit);

        return [
            'items' => $pagedItems,
            'totalRows' => $totalRows
        ];
    }

    private static function getPropertyValue(object|array $item, string $property): mixed
    {
        if (is_array($item)) {
            return $item[$property] ?? '';
        }
        
        $getter = 'get' . ucfirst($property);
        if (method_exists($item, $getter)) {
            return $item->$getter();
        }
        
        // Check for public property or dynamic property
        if (property_exists($item, $property) || isset($item->$property)) {
            return $item->$property;
        }

        return '';
    }
}
