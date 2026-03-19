<?php

namespace Database\Seeders;

use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;
use Illuminate\Database\Seeder;

class LocationsSeeder extends Seeder
{
    /**
     * Populate the Country, State & City tables with location data
     *
     * These were generated with ChatGPT o1 on the 5th Jan 2025
     */
    public function run(): void
    {
        $countries = [
            [
                'country' => 'Ireland',
                'shortcode' => 'ie',
                'states' => [
                    'Cork' => [
                        'Cork City',
                        'Cobh',
                        'Midleton',
                        'Youghal',
                    ],
                    'Dublin' => [
                        'Dublin City',
                        'Dun Laoghaire',
                        'Swords',
                        'Malahide',
                    ],
                    'Galway' => [
                        'Galway City',
                        'Tuam',
                        'Loughrea',
                        'Oranmore',
                    ],
                    'Kerry' => [
                        'Tralee',
                        'Killarney',
                        'Listowel',
                        'Castleisland',
                    ],
                ]
            ],

            [
                'country' => 'United States',
                'shortcode' => 'us',
                'states' => [
                    'California' => [
                        'Los Angeles',
                        'San Francisco',
                        'San Diego',
                        'Sacramento',
                    ],
                    'Texas' => [
                        'Houston',
                        'Dallas',
                        'Austin',
                        'San Antonio',
                    ],
                    'New York' => [
                        'New York City',
                        'Buffalo',
                        'Rochester',
                        'Albany',
                    ],
                    'Florida' => [
                        'Miami',
                        'Orlando',
                        'Tampa',
                        'Jacksonville',
                    ],
                ]
            ],
            [
                'country' => 'Canada',
                'shortcode' => 'ca',
                'states' => [
                    'Ontario' => [
                        'Toronto',
                        'Ottawa',
                        'Hamilton',
                        'Kitchener',
                    ],
                    'Quebec' => [
                        'Montreal',
                        'Quebec City',
                        'Laval',
                        'Gatineau',
                    ],
                    'British Columbia' => [
                        'Vancouver',
                        'Victoria',
                        'Kelowna',
                        'Kamloops',
                    ],
                    'Alberta' => [
                        'Calgary',
                        'Edmonton',
                        'Red Deer',
                        'Lethbridge',
                    ],
                ]
            ],
            [
                'country' => 'Brazil',
                'shortcode' => 'br',
                'states' => [
                    'Sao Paulo' => [
                        'São Paulo',
                        'Campinas',
                        'Santos',
                        'Sorocaba',
                    ],
                    'Rio de Janeiro' => [
                        'Rio de Janeiro',
                        'Niteroi',
                        'Petropolis',
                        'Nova Iguacu',
                    ],
                    'Minas Gerais' => [
                        'Belo Horizonte',
                        'Uberlândia',
                        'Contagem',
                        'Juiz de Fora',
                    ],
                    'Bahia' => [
                        'Salvador',
                        'Feira de Santana',
                        'Itabuna',
                        'Ilhéus',
                    ],
                ]
            ],
            [
                'country' => 'Netherlands',
                'shortcode' => 'nl',
                'states' => [
                    'North Holland' => [
                        'Amsterdam',
                        'Haarlem',
                        'Alkmaar',
                        'Zaandam',
                    ],
                    'South Holland' => [
                        'Rotterdam',
                        'The Hague',
                        'Leiden',
                        'Delft',
                    ],
                    'Utrecht' => [
                        'Utrecht',
                        'Amersfoort',
                        'Nieuwegein',
                        'Zeist',
                    ],
                    'Gelderland' => [
                        'Nijmegen',
                        'Arnhem',
                        'Apeldoorn',
                        'Ede',
                    ],
                ]
            ],
            [
                'country' => 'United Kingdom',
                'shortcode' => 'uk',
                'states' => [
                    'England' => [
                        'London',
                        'Manchester',
                        'Liverpool',
                        'Birmingham',
                    ],
                    'Scotland' => [
                        'Edinburgh',
                        'Glasgow',
                        'Aberdeen',
                        'Dundee',
                    ],
                    'Wales' => [
                        'Cardiff',
                        'Swansea',
                        'Newport',
                        'Wrexham',
                    ],
                    'Northern Ireland' => [
                        'Belfast',
                        'Londonderry',
                        'Lisburn',
                        'Newtownabbey',
                    ],
                ]
            ],
            [
                'country' => 'Germany',
                'shortcode' => 'de',
                'states' => [
                    'Bavaria' => [
                        'Munich',
                        'Nuremberg',
                        'Augsburg',
                        'Regensburg',
                    ],
                    'Berlin' => [
                        'Berlin',
                        'Pankow',
                        'Charlottenburg',
                        'Friedrichshain',
                    ],
                    'Hamburg' => [
                        'Hamburg',
                        'Altona',
                        'Wandsbek',
                        'Eimsbüttel',
                    ],
                    'Hesse' => [
                        'Frankfurt',
                        'Wiesbaden',
                        'Darmstadt',
                        'Kassel',
                    ],
                ]
            ],
            [
                'country' => 'France',
                'shortcode' => 'fr',
                'states' => [
                    'Ile-de-France' => [
                        'Paris',
                        'Boulogne-Billancourt',
                        'Versailles',
                        'Saint-Denis',
                    ],
                    'Provence-Alpes-Cote d\'Azur' => [
                        'Marseille',
                        'Nice',
                        'Toulon',
                        'Aix-en-Provence',
                    ],
                    'Occitanie' => [
                        'Toulouse',
                        'Montpellier',
                        'Nîmes',
                        'Perpignan',
                    ],
                    'Auvergne-Rhone-Alpes' => [
                        'Lyon',
                        'Grenoble',
                        'Saint-Étienne',
                        'Clermont-Ferrand',
                    ],
                ]
            ],
            [
                'country' => 'Australia',
                'shortcode' => 'au',
                'states' => [
                    'New South Wales' => [
                        'Sydney',
                        'Newcastle',
                        'Wollongong',
                        'Bathurst',
                    ],
                    'Victoria' => [
                        'Melbourne',
                        'Geelong',
                        'Ballarat',
                        'Bendigo',
                    ],
                    'Queensland' => [
                        'Brisbane',
                        'Gold Coast',
                        'Cairns',
                        'Townsville',
                    ],
                    'Western Australia' => [
                        'Perth',
                        'Fremantle',
                        'Bunbury',
                        'Broome',
                    ],
                ]
            ],
            [
                'country' => 'Japan',
                'shortcode' => 'jp',
                'states' => [
                    'Tokyo' => [
                        'Tokyo',
                        'Hachioji',
                        'Tachikawa',
                        'Machida',
                    ],
                    'Kanagawa' => [
                        'Yokohama',
                        'Kawasaki',
                        'Sagamihara',
                        'Fujisawa',
                    ],
                    'Osaka' => [
                        'Osaka',
                        'Sakai',
                        'Higashiosaka',
                        'Moriguchi',
                    ],
                    'Aichi' => [
                        'Nagoya',
                        'Toyota',
                        'Okazaki',
                        'Ichinomiya',
                    ],
                ]
            ],
            [
                'country' => 'India',
                'shortcode' => 'in',
                'states' => [
                    'Maharashtra' => [
                        'Mumbai',
                        'Pune',
                        'Nagpur',
                        'Nashik',
                    ],
                    'Delhi' => [
                        'Delhi',
                        'New Delhi',
                        'Dwarka',
                        'Karol Bagh',
                    ],
                    'Karnataka' => [
                        'Bengaluru',
                        'Mysuru',
                        'Mangalore',
                        'Hubli',
                    ],
                    'Tamil Nadu' => [
                        'Chennai',
                        'Coimbatore',
                        'Madurai',
                        'Salem',
                    ],
                ]
            ],
            [
                'country' => 'South Africa',
                'shortcode' => 'za',
                'states' => [
                    'Gauteng' => [
                        'Johannesburg',
                        'Pretoria',
                        'Soweto',
                        'Benoni',
                    ],
                    'Western Cape' => [
                        'Cape Town',
                        'Stellenbosch',
                        'Paarl',
                        'George',
                    ],
                    'Eastern Cape' => [
                        'Port Elizabeth',
                        'East London',
                        'Grahamstown',
                        'Uitenhage',
                    ],
                    'KwaZulu-Natal' => [
                        'Durban',
                        'Pietermaritzburg',
                        'Richards Bay',
                        'Newcastle',
                    ],
                ]
            ],
            [
                'country' => 'Mexico',
                'shortcode' => 'mx',
                'states' => [
                    'Mexico City' => [
                        'Mexico City',
                        'Coyoacan',
                        'Xochimilco',
                        'Tlalpan',
                    ],
                    'Jalisco' => [
                        'Guadalajara',
                        'Zapopan',
                        'Tlaquepaque',
                        'Puerto Vallarta',
                    ],
                    'Nuevo Leon' => [
                        'Monterrey',
                        'San Nicolás de los Garza',
                        'Guadalupe',
                        'Apodaca',
                    ],
                    'Puebla' => [
                        'Puebla',
                        'Tehuacan',
                        'Cholula',
                        'Atlixco',
                    ],
                ]
            ],
            [
                'country' => 'Italy',
                'shortcode' => 'it',
                'states' => [
                    'Lombardy' => [
                        'Milan',
                        'Bergamo',
                        'Brescia',
                        'Monza',
                    ],
                    'Lazio' => [
                        'Rome',
                        'Latina',
                        'Viterbo',
                        'Frosinone',
                    ],
                    'Campania' => [
                        'Naples',
                        'Salerno',
                        'Caserta',
                        'Avellino',
                    ],
                    'Veneto' => [
                        'Venice',
                        'Verona',
                        'Padua',
                        'Vicenza',
                    ],
                ]
            ],
            [
                'country' => 'Spain',
                'shortcode' => 'es',
                'states' => [
                    'Catalonia' => [
                        'Barcelona',
                        'Tarragona',
                        'Girona',
                        'Lleida',
                    ],
                    'Madrid' => [
                        'Madrid',
                        'Alcalá de Henares',
                        'Fuenlabrada',
                        'Getafe',
                    ],
                    'Andalusia' => [
                        'Seville',
                        'Málaga',
                        'Córdoba',
                        'Granada',
                    ],
                    'Valencia' => [
                        'Valencia',
                        'Alicante',
                        'Elche',
                        'Castellón de la Plana',
                    ],
                ]
            ],
            [
                'country' => 'New Zealand',
                'shortcode' => 'nz',
                'states' => [
                    'Auckland' => [
                        'Auckland',
                        'Manukau',
                        'Waitakere',
                        'North Shore',
                    ],
                    'Wellington' => [
                        'Wellington',
                        'Lower Hutt',
                        'Upper Hutt',
                        'Porirua',
                    ],
                    'Canterbury' => [
                        'Christchurch',
                        'Timaru',
                        'Ashburton',
                        'Rangiora',
                    ],
                    'Otago' => [
                        'Dunedin',
                        'Queenstown',
                        'Wanaka',
                        'Oamaru',
                    ],
                ]
            ],
            [
                'country' => 'South Korea',
                'shortcode' => 'kr',
                'states' => [
                    'Seoul' => [
                        'Seoul',
                        'Gangnam-gu',
                        'Jongno-gu',
                        'Mapo-gu',
                    ],
                    'Busan' => [
                        'Busan',
                        'Haeundae-gu',
                        'Suyeong-gu',
                        'Dongnae-gu',
                    ],
                    'Incheon' => [
                        'Incheon',
                        'Yeonsu-gu',
                        'Namdong-gu',
                        'Bupyeong-gu',
                    ],
                    'Daegu' => [
                        'Daegu',
                        'Suseong-gu',
                        'Dalseo-gu',
                        'Buk-gu',
                    ],
                ]
            ],
            [
                'country' => 'Argentina',
                'shortcode' => 'ar',
                'states' => [
                    'Buenos Aires' => [
                        'Buenos Aires',
                        'La Plata',
                        'Mar del Plata',
                        'Bahía Blanca',
                    ],
                    'Córdoba' => [
                        'Córdoba',
                        'Villa Carlos Paz',
                        'Río Cuarto',
                        'Alta Gracia',
                    ],
                    'Santa Fe' => [
                        'Rosario',
                        'Santa Fe',
                        'Rafaela',
                        'Venado Tuerto',
                    ],
                    'Mendoza' => [
                        'Mendoza',
                        'San Rafael',
                        'Godoy Cruz',
                        'Las Heras',
                    ],
                ]
            ],
        ];

        foreach ($countries as $countryData)
        {
            $country = Country::firstOrCreate([
                'country' => $countryData['country'],
                'shortcode' => $countryData['shortcode'],
                'manual_verify' => 1,
            ]);

            $countryId = $country->id;

            foreach ($countryData['states'] as $stateName => $cities)
            {
                $state = State::firstOrCreate([
                    'state' => $stateName,
                    'country_id' => $countryId,
                ]);

                $stateId = $state->id;

                foreach ($cities as $cityName) {
                    City::firstOrCreate([
                        'city' => $cityName,
                        'state_id' => $stateId,
                        'country_id' => $countryId,
                    ]);
                }
            }
        }
    }
}
