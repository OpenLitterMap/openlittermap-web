<?php

namespace App\Enums;

enum CategoryKey: string
{
    // ── Active categories (in TagsConfig, visible to users) ──
    case Alcohol = 'alcohol';
    case Art = 'art';
    case Civic = 'civic';
    case Coffee = 'coffee';
    case Dumping = 'dumping';
    case Electronics = 'electronics';
    case Food = 'food';
    case Industrial = 'industrial';
    case Marine = 'marine';
    case Medical = 'medical';
    case Other = 'other';
    case Pets = 'pets';
    case Sanitary = 'sanitary';
    case Smoking = 'smoking';
    case Softdrinks = 'softdrinks';
    case Vehicles = 'vehicles';

    // ── System categories (not shown in UI) ──
    case Brands = 'brands';
    case Material = 'material';
    case Unclassified = 'unclassified';

    // ── Deprecated aliases (v4 keys, resolved by ClassifyTagsService) ──
    case Automobile = 'automobile';
    case Coastal = 'coastal';
    case Dogshit = 'dogshit';
    case Stationery = 'stationery';
}
