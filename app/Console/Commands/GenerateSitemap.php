<?php

namespace App\Console\Commands;

use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class GenerateSitemap extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sitemap:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generates the sitemap automatically.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $sitemap = Sitemap::create()
            ->add($this->url(''))
            ->add($this->url('about'))
            ->add($this->url('global'))
            ->add($this->url('world'))
            ->add($this->url('signup'));

        $this->addCountries($sitemap);

        $this->addStates($sitemap);

        $this->addCities($sitemap);

        $sitemap->writeToFile(public_path('sitemap.xml'));

        $this->line('Done!');

        return 0;
    }

    private function url($url): Url
    {
        $site = 'https://openlittermap.com/';

        return Url::create($site . $url)
            ->setLastModificationDate(Carbon::yesterday())
            ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
            ->setPriority(1);
    }

    private function addCountries(Sitemap $sitemap): void
    {
        $countries = Country::where('manual_verify', '1')->get();

        foreach ($countries as $country) {
            $sitemap->add($this->url("world/{$country->country}"));
        }
    }

    private function addStates(Sitemap $sitemap): void
    {
        $states = State::with('country')
            ->where([
                'manual_verify' => 1,
                ['total_litter', '>', 0],
                ['total_contributors', '>', 0]
            ])
            ->get();

        foreach ($states as $state) {
            $sitemap->add($this->url("world/{$state->country->country}/{$state->state}"));
        }
    }

    private function addCities(Sitemap $sitemap): void
    {
        $cities = City::with('state.country')
            ->where([
                ['total_images', '>', 0],
                ['total_litter', '>', 0],
                ['total_contributors', '>', 0]
            ])
            ->get();

        foreach ($cities as $city) {
            $sitemap->add($this->url(
                "world/{$city->state->country->country}/{$city->state->state}/{$city->city}/map")
            );
        }
    }
}
