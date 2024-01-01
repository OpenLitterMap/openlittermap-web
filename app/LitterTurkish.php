<?php

namespace App;

final class LitterTurkish{
    private $json = '
{
    "Smoking": {
        "table": "smoking",
        "id": "smoking_id",
        "class": "Smoking",
        "total": "total_smoking",
        "types": {
            "Sigara/İzmarit": {
                "col": "butts"
            },
            "Çakmak": {
                "col": "lighters"
            },
            "Sigara Kutusu": {
                "col": "cigaretteBox"
            },
            "Tütün paketi": {
                "col": "tobaccoPouch"
            },
            "Sigara sarma kağıdı": {
                "col": "skins"
            },
            "Plastik Ambalaj": {
                "col": "smoking_plastic"
            },
            "Sigara filtresi": {
                "col": "filters"
            },
            "Sigara filtre kutusu": {
                "col": "filterbox"
            },
            "Sigara (diğer)": {
                "col": "smokingOther"
            }
        }
    },
    "Alcohol": {
        "table": "alcohol",
        "id": "alcohol_id",
        "class": "Alcohol",
        "total": "total_alcohol",
        "types": {
            "Bira (Cam Şişe)": {
                "col": "beerBottle"
            },
            "Alkollü içkiler (cam şişe)": {
                "col": "spiritBottle"
            },
            "Şarap (cam şişe)": {
                "col": "wineBottle"
            },
            "Bira kutusu": {
                "col": "beerCan"
            },
            "Kırık cam": {
                "col": "brokenGlass"
            },
            "Bira şişesi kapağı": {
                "col": "bottleTops"
            },
            "Kağıt ambalaj": {
                "col": "paperCardAlcoholPackaging"
            },
            "Plastik ambalaj": {
                "col": "plasticAlcoholPackaging"
            },
            "Alkol (diğer)": {
                "col": "alcoholOther"
            }
        }
    },
    "Coffee": {
        "id": "coffee_id",
        "table": "coffee",
        "class": "Coffee",
        "total": "total_coffee",
        "types": {
            "Kahve bardağı": {
                "col": "coffeeCups"
            },
            "Kahve bardağı kapağı": {
                "col": "coffeeLids"
            },
            "Kahve (diğer)": {
                "col": "coffeeOther"
            }
        }
    },
    "Food": {
        "id": "food_id",
        "table": "food",
        "class": "Food",
        "total": "total_food",
        "types": {
            "Tatlı abur cubur ambalajı": {
                "col": "sweetWrappers"
            },
            "Kağıt/karton ambalaj": {
                "col": "paperFoodPackaging"
            },
            "Plastik ambalaj": {
                "col": "plasticFoodPackaging"
            },
            "Plastik çatal bıçak": {
                "col": "plasticCutlery"
            },
            "Cips paketi (küçük)": {
                "col": "crisp_small"
            },
            "Cips paketi (büyük)": {
                "col": "crisp_large"
            },
            "Köpük": {
                "col": "styrofoam_plate"
            },
            "Peçete": {
                "col": "napkins"
            },
            "Sos paketi": {
                "col": "sauce_packet"
            },
            "Cam şişe": {
                "col": "glass_jar"
            },
            "Cam şişe kapağı": {
                "col": "glass_jar_lid"
            },
            "Gıda (diğer)": {
                "col": "foodOther"
            }
        }
    },
    "Soft-Drinks": {
        "id": "softdrinks_id",
        "table": "soft_drinks",
        "class": "SoftDrinks",
        "total": "total_softdrinks",
        "types": {
            "Plastik su şişesi": {
                "col": "waterBottle"
            },
            "Plastik gazlı içecek şişesi": {
                "col": "fizzyDrinkBottle"
            },
            "Teneke gazlı içecek kutusu": {
                "col": "tinCan"
            },
            "Şişe kapağı": {
                "col": "bottleLid"
            },
            "Şişe marka etiketi": {
                "col": "bottleLabel"
            },
            "Sporcu içeceği şişesi": {
                "col": "sportsDrink"
            },
            "Pipet": {
                "col": "straws"
            },
            "Plastik bardak": {
                "col": "plastic_cups"
            },
            "Plastik bardak kapağı": {
                "col": "plastic_cup_tops"
            },
            "Şüt şişesi": {
                "col": "milk_bottle"
            },
            "Süt kartonu": {
                "col": "milk_carton"
            },
            "Karton bardak": {
                "col": "paper_cups"
            },
            "Meyve suyu kartonu": {
                "col": "juice_cartons"
            },
            "Meyve suyu şişesi": {
                "col": "juice_bottles"
            },
            "Meyve suyu paketi": {
                "col": "juice_packet"
            },
            "Soğu çay şişesi": {
                "col": "ice_tea_bottles"
            },
            "Teneke soğuk çay": {
                "col": "ice_tea_can"
            },
            "Enerji içeceği tenekesi": {
                "col": "energy_can"
            },
            "Karton Köpük": {
                "col": "styro_cup"
            },
            "Alkolsüz içecekler (diğer)": {
                "col": "softDrinkOther"
            }
        }
    },
    "Sanitary": {
        "id": "sanitary_id",
        "table": "sanitary",
        "class": "Sanitary",
        "total": "total_sanitary",
        "types": {
            "Prezervatif": {
                "col": "condoms"
            },
            "Çocuk bezi": {
                "col": "nappies"
            },
            "Kadın pedi": {
                "col": "menstral"
            },
            "Deodorant": {
                "col": "deodorant"
            },
            "Kulak temizleme çöpü": {
                "col": "ear_swabs"
            },
            "Kürdan": {
                "col": "tooth_pick"
            },
            "Diş fırçası": {
                "col": "tooth_brush"
            },
            "Hijyen ürünleri (diğer)": {
                "col": "sanitaryOther"
            }
        }
    },
    "Other": {
        "id": "other_id",
        "table": "others",
        "class": "Other",
        "total": "total_other",
        "types": {
            "Köpek dışkısı": {
                "col": "dogshit"
            },
            "Tanımlanamayan plastik": {
                "col": "plastic"
            },
            "Çöp yığını": {
                "col": "dump"
            },
            "Metal obje": {
                "col": "metal"
            },
            "Plastik poşet": {
                "col": "plastic_bags"
            },
            "Seçim afişi": {
                "col": "election_posters"
            },
            "İndirim afişi": {
                "col": "forsale_posters"
            },
            "Kitap": {
                "col": "books"
            },
            "Dergi": {
                "col": "magazine"
            },
            "Kağıt": {
                "col": "paper"
            },
            "Stasyoner": {
                "col": "stationary"
            },
            "Bulaşık deterjanı şişesi": {
                "col": "washing_up"
            },
            "Saç tokası": {
                "col": "hair_tie"
            },
            "Müzik dinleme kulaklığı": {
                "col": "ear_plugs"
            },
            "Diğer (Diğer)": {
                "col": "other"
            }
        }
    },
    "Coastal": {
        "id": "coastal_id",
        "table": "costal",
        "class": "Coastal",
        "total": "total_coastal",
        "types": {
            "Mikroplastik": {
                "col": "microplastics"
            },
            "Medyum plastik": {
                "col": "mediumplastics"
            },
            "Makroplastik": {
                "col": "macroplastics"
            },
            "Kısa halat": {
                "col": "rope_small"
            },
            "Orta boy halat": {
                "col": "rope_medium"
            },
            "Uzun halat": {
                "col": "rope_large"
            },
            "Balıkçı ağı": {
                "col": "fishing_gear_nets"
            },
            "Şamandra": {
                "col": "buoys"
            },
            "Parçalanmaya yüz tutmuş plastik şişe": {
                "col": "degraded_plasticbottle"
            },
            "Parçalanmaya yüz tutmuş plastik poşet": {
                "col": "degraded_plasticbag"
            },
            "Parçalanmaya yüz tutmuş plastik pipet": {
                "col": "degraded_straws"
            },
            "Parçalanmaya yüz tutmuş çakmak": {
                "col": "degraded_lighters"
            },
            "Balon": {
                "col": "balloons"
            },
            "Lego": {
                "col": "lego"
            },
            "Ateşli silah fişeği": {
                "col": "shotgun_cartridges"
            },
            "Kısa Köpük": {
                "col": "styro_small"
            },
            "Orta Köpük": {
                "col": "styro_medium"
            },
            "Uzun Köpük": {
                "col": "styro_large"
            },
            "Kıyısal (Diğer)": {
                "col": "coastal_other"
            }
        }
    },
    "Art": {
        "id": "art_id",
        "table": "arts",
        "class": "Art",
        "total": "total_art",
        "types": {
            "Sanat": {
                "col": "item"
            }
        }
    },
    "Brands": {
        "id": "brands_id",
        "table": "brands",
        "class": "Brand",
        "total": "total_brands",
        "types": {
                        "Adidas": {
                "col": "adidas"
            },
            "Amazon": {
                "col": "amazon"
            },
            "Aldi": {
                "col": "aldi"
            },
            "Apple": {
                "col": "apple"
            },
            "Applegreen": {
                "col": "applegreen"
            },
            "Asahi": {
                "col": "asahi"
            },
            "Avoca": {
                "col": "avoca"
            },
            "Ballygowan": {
                "col": "ballygowan"
            },
            "Bewleys": {
                "col": "bewleys"
            },
            "Brambles": {
                "col": "brambles"
            },
            "Budweiser": {
                "col": "budweiser"
            },
            "Bulmers": {
                "col": "bulmers"
            },
            "BurgerKing": {
                "col": "burgerking"
            },
            "Butlers": {
                "col": "butlers"
            },
            "Cadburys": {
                "col": "cardburys"
            },
            "Cafe-Nero": {
                "col": "cafe_nero"
            },
            "Carlsberg": {
                "col": "carlsberg"
            },
            "Centra": {
                "col": "centra"
            },
            "Camel": {
                "col": "camel"
            },
            "CircleK": {
                "col": "circlek"
            },
            "Coca-Cola": {
                "col": "coke"
            },
            "Colgate": {
                "col": "colgate"
            },
            "Coles": {
                "col": "coles"
            },
            "Corona": {
                "col": "corona"
            },
            "Costa": {
                "col": "costa"
            },
            "Doritos": {
                "col": "doritos"
            },
            "DrPepper": {
                "col": "drpepper"
            },
            "Dunnes": {
                "col": "dunnes"
            },
            "Duracell": {
                "col": "duracell"
            },
            "Durex": {
                "col": "durex"
            },
            "Esquires": {
                "col": "esquires"
            },
            "Evian": {
                "col": "evian"
            },
            "Fosters": {
                "col": "fosters"
            },
            "Frank-and-Honest": {
                "col": "frank_and_honest"
            },
            "Frito-Lay": {
                "col": "fritolay"
            },
            "Gatorade": {
                "col": "gatorade"
            },
            "Gillette": {
                "col": "gillette"
            },
            "Guinness": {
                "col": "guinness"
            },
            "Haribo": {
                "col": "haribo"
            },
            "Heineken": {
                "col": "heineken"
            },
            "Insomnia": {
                "col": "insomnia"
            },
            "Kellogs": {
                "col": "kellogs"
            },
            "KFC": {
                "col": "kfc"
            },
            "Lego": {
                "col": "lego"
            },
            "Lidl": {
                "col": "lidl"
            },
            "LindenVillage": {
                "col": "lindenvillage"
            },
            "Lolly-and-Cookes": {
                "col": "lolly_and_cookes"
            },
            "Loreal": {
                "col": "loreal"
            },
            "Lucozade": {
                "col": "lucozade"
            },
            "Nero": {
                "col": "nero"
            },
            "Nescafe": {
                "col": "nescafe"
            },
            "Nestle": {
                "col": "nestle"
            },
            "Marlboro": {
                "col": "marlboro"
            },
            "Mars": {
                "col": "mars"
            },
            "McDonalds": {
                "col": "mcdonalds"
            },
            "Nike": {
                "col": "nike"
            },
            "O-Briens": {
                "col": "obriens"
            },
            "Pepsi": {
                "col": "pepsi"
            },
            "Powerade": {
                "col": "powerade"
            },
            "Redbull": {
                "col": "redbull"
            },
            "Ribena": {
                "col": "ribena"
            },
            "Samsung": {
                "col": "samsung"
            },
            "Sainsburys": {
                "col": "sainsburys"
            },
            "Spar": {
                "col": "spar"
            },
            "Starbucks": {
                "col": "starbucks"
            },
            "Stella": {
                "col": "stella"
            },
            "Subway": {
                "col": "subway"
            },
            "Supermacs": {
                "col": "supermacs"
            },
            "Tayto": {
                "col": "tayto"
            },
            "Tesco": {
                "col": "tesco"
            },
            "Thins": {
                "col": "thins"
            },
            "Volvic": {
                "col": "volvic"
            },
            "Waitrose": {
                "col": "waitrose"
            },
            "Wilde-and-Greene": {
                "col": "wilde_and_greene"
            },
            "Woolworths": {
                "col": "woolworths"
            },
            "Wrigleys": {
                "col": "wrigleys"
            }
        }
    },

    "TrashDog": {
        "id": "trashdog_id",
        "table": "trash_dogs",
        "class": "TrashDog",
        "total": "total_trashdog",
        "types": {
            "Köpek çöp kutusu": {
                "col": "trashdog"
            },
            "Littercat": {
                "col": "littercat"
            },
            "LitterDuck": {
                "col": "duck"
            }
        }
    }
}';

    private $jsonDecoded;

    public static function INSTANCE(){
        static $inst = null;
        if ($inst === null) {
            $inst = new LitterTurkish();
        }

        return $inst;
    }

    public function __construct(){
        $this->jsonDecoded = json_decode((string) $this->json);

    }

    public function getDecodedJSON(){ return $this->jsonDecoded; }
}
