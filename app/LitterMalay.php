<?php

namespace App;

final class LitterMalay{
    private $json = '
{
    "Smoking": {
        "table": "smoking",
        "id": "smoking_id",
        "class": "Smoking",
        "total": "total_smoking",
        "types": {
            "Rokok": {
                "col": "butts"
            },
            "Pemetik Api": {
                "col": "lighters"
            },
            "Kotak Rokok": {
                "col": "cigaretteBox"
            },
            "Uncang Tembakau": {
                "col": "tobaccoPouch"
            },
            "Kertas Gulung Tembakau": {
                "col": "skins"
            },
            "Plastik Pembungkus": {
                "col": "smoking_plastic"
            },
            "Penapis": {
                "col": "filters"
            },
            "Kotak Penapis": {
                "col": "filterbox"
            },
            "Rokok (lain-lain)": {
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
            "Botol Gelas (bir)": {
                "col": "beerBottle"
            },
            "Botol Gelas (spirit)": {
                "col": "spiritBottle"
            },
            "Botol Gelas (wain)": {
                "col": "wineBottle"
            },
            "Tin Bir": {
                "col": "beerCan"
            },
            "Serpihan Gelas": {
                "col": "brokenGlass"
            },
            "Penutup Botol Bir": {
                "col": "bottleTops"
            },
            "Pembungkus kertas": {
                "col": "paperCardAlcoholPackaging"
            },
            "Pembungkus plastik": {
                "col": "plasticAlcoholPackaging"
            },
            "Alkohol (lain-lain)": {
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
            "Cawan kopi": {
                "col": "coffeeCups"
            },
            "Penutup cawan": {
                "col": "coffeeLids"
            },
            "Kopi (lain-lain)": {
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
            "Pembalut gula-gula": {
                "col": "sweetWrappers"
            },
            "Pembungkus Kertas/Kadbod": {
                "col": "paperFoodPackaging"
            },
            "Pembungkus Plastik": {
                "col": "plasticFoodPackaging"
            },
            "Kutleri Plastik": {
                "col": "plasticCutlery"
            },
            "Paket Kerepek/Keropok (kecil)": {
                "col": "crisp_small"
            },
            "Paket Kerepek/Keropok (besar)": {
                "col": "crisp_large"
            },
            "Styrofom/Polystyrene": {
                "col": "styrofoam_plate"
            },
            "Kertas Tisu": {
                "col": "napkins"
            },
            "Paket Sos": {
                "col": "sauce_packet"
            },
            "Bekas gelas": {
                "col": "glass_jar"
            },
            "Penutup bekas gelas": {
                "col": "glass_jar_lid"
            },
            "Makanan (lain-lain)": {
                "col": "foodOther"
            }
        }
    },
    "SoftDrinks": {
        "id": "softdrinks_id",
        "table": "soft_drinks",
        "class": "SoftDrinks",
        "total": "total_softdrinks",
        "types": {
            "Botol Air Minuman/Mineral Plastik": {
                "col": "waterBottle"
            },
            "Botol Minuman Berkarbonat": {
                "col": "fizzyDrinkBottle"
            },
            "Tin Aluminium": {
                "col": "tinCan"
            },
            "Penutup Botol": {
                "col": "bottleLid"
            },
            "Label pada Botol": {
                "col": "bottleLabel"
            },
            "Botol Minuman Sukan/Isotonik": {
                "col": "sportsDrink"
            },
            "Penyedut Minuman": {
                "col": "straws"
            },
            "Cawan Plastik Pakai Buang": {
                "col": "plastic_cups"
            },
            "Penutup Cawan Plastik Pakai Buang": {
                "col": "plastic_cup_tops"
            },
            "Botol Susu": {
                "col": "milk_bottle"
            },
            "Karton/Kotak Susu": {
                "col": "milk_carton"
            },
            "Cawan Kertas": {
                "col": "paper_cups"
            },
            "Karton/Kotak Jus": {
                "col": "juice_cartons"
            },
            "Botol Jus": {
                "col": "juice_bottles"
            },
            "Paket Jus": {
                "col": "juice_packet"
            },
            "Botol Teh Ais": {
                "col": "ice_tea_bottles"
            },
            "Tin Aluminium Teh Ais": {
                "col": "ice_tea_can"
            },
            "Tin Aluminium Minuman Tenaga": {
                "col": "energy_can"
            },
            "Polystyrene Cup": {
                "col": "styro_cup"
            },
            "Minuman Ringan (lain-lain)": {
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
            "Kondom": {
                "col": "condoms"
            },
            "Lampin bayi": {
                "col": "nappies"
            },
            "Produk Penjagaan Wanita": {
                "col": "menstral"
            },
            "Deodoran": {
                "col": "deodorant"
            },
            "Putik Kapas": {
                "col": "ear_swabs"
            },
            "Pencungkil Gigi": {
                "col": "tooth_pick"
            },
            "Berus Gigi": {
                "col": "tooth_brush"
            },
            "Produk Penjagaan Diri (lain-lain)": {
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
            "Tahi Anjing": {
                "col": "dogshit"
            },
            "Plastik yang tak dapat dikenalpasti": {
                "col": "plastic"
            },
            "Sampah Pukal/Tempat Pembuangan Sampah": {
                "col": "dump"
            },
            "Objek logam": {
                "col": "metal"
            },
            "Beg Plastik": {
                "col": "plastic_bags"
            },
            "Poster Pilihan Raya": {
                "col": "election_posters"
            },
            "Poster Jualan": {
                "col": "forsale_posters"
            },
            "Buku": {
                "col": "books"
            },
            "Majalah": {
                "col": "magazine"
            },
            "Kertas": {
                "col": "paper"
            },
            "Alat Tulis": {
                "col": "stationary"
            },
            "Botol Bahan Pencuci": {
                "col": "washing_up"
            },
            "Pengikat Rambut": {
                "col": "hair_tie"
            },
            "Earphone (muzik)": {
                "col": "ear_plugs"
            },
            "Lain (lain-lain)": {
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
            "Partikel Plastik": {
                "col": "mediumplastics"
            },
            "Makroplastik": {
                "col": "macroplastics"
            },
            "Tali halus": {
                "col": "rope_small"
            },
            "Tali sederhana": {
                "col": "rope_medium"
            },
            "Tali tebal": {
                "col": "rope_large"
            },
            "Alatan memancing/pukat": {
                "col": "fishing_gear_nets"
            },
            "Boya": {
                "col": "buoys"
            },
            "Serpihan Botol Plastik": {
                "col": "degraded_plasticbottle"
            },
            "Cebisan Beg Plastik": {
                "col": "degraded_plasticbag"
            },
            "Cebisan Penyedut Minuman": {
                "col": "degraded_straws"
            },
            "Pemetik Api": {
                "col": "degraded_lighters"
            },
            "Belon": {
                "col": "balloons"
            },
            "Lego": {
                "col": "lego"
            },
            "Kelongsong Peluru": {
                "col": "shotgun_cartridges"
            },
            "Styrofoam halus": {
                "col": "styro_small"
            },
            "Styrofoam sederhana": {
                "col": "styro_medium"
            },
            "Styrofoam tebal": {
                "col": "styro_large"
            },
            "Pantai (lain-lain)": {
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
            "Seni": {
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
            "Camel": {
                "col": "camel"
            },
            "Carlsberg": {
                "col": "carlsberg"
            },
            "Centra": {
                "col": "centra"
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
            "Anjing Pengutip Sampah": {
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
            $inst = new LitterMalay();
        }

        return $inst;
    }

    public function __construct(){
        $this->jsonDecoded = json_decode((string) $this->json);
    }

    public function getDecodedJSON(){ return $this->jsonDecoded; }
}


//// USAGE

// $jsonDecoded = Litterrata::INSTANCE()->getDecodedJSON();

// $joking = "smoking";
// $lable  = "table";
// echo $jsonDecoded->$joking->$lable . "<br />\n";
// echo $jsonDecoded->smoking->table  . "<br />\n";

//// USAGE
