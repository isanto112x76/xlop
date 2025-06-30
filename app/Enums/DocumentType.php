<?php

namespace App\Enums;

enum DocumentType: string
{
    // Zakup
    case PZ = 'PZ'; // Przyjęcie Zewnętrzne
    case FVZ = 'FVZ'; // Faktura Zakupu

    // Sprzedaż
    case WZ = 'WZ'; // Wydanie Zewnętrzne
    case FS = 'FS'; // Faktura Sprzedaży

    // Przesunięcia
    case MM = 'MM'; // Przesunięcie Międzymagazynowe

    // Operacje wewnętrzne
    case PW = 'PW'; // Przyjęcie Wewnętrzne
    case RW = 'RW'; // Rozchód Wewnętrzny

    // Zwroty
    case ZW = 'ZW'; // Zwrot od klienta
    case ZRW = 'ZRW'; // Zwrot do dostawcy (lub ZP)

    // Inwentaryzacja
    case INW = 'INW'; // Inwentaryzacja

    /**
     * Zwraca czytelniejszą etykietę dla danego typu dokumentu.
     */
    public function label(): string
    {
        return match ($this) {
            self::PZ => 'Przyjęcie Zewnętrzne',
            self::FVZ => 'Faktura Zakupu',
            self::WZ => 'Wydanie Zewnętrzne',
            self::FS => 'Faktura Sprzedaży',
            self::MM => 'Przesunięcie Międzymagazynowe',
            self::PW => 'Przyjęcie Wewnętrzne',
            self::RW => 'Rozchód Wewnętrzny',
            self::ZW => 'Zwrot od Klienta',
            self::ZRW => 'Zwrot do Dostawcy',
            self::INW => 'Inwentaryzacja',
        };
    }
    public function getLabel(): string
    {
        return $this->label();
    }
}
