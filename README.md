
<img src="https://openlittermap.com/assets/logo_small.png" />
<h3>About OpenLitterMap</h3>
<hr>
<p>OpenLitterMap is an open, interactive, and accessible database of the world's litter and plastic pollution.</p>
<p>We are building a fun data-collection experience to harness the unprecedented potential of citizen scientists around the world.</p>
<p>We believe that science on pollution should be an open, transparent and democratic process- not limited or controlled by anyone or any group.</p>
<p>If you would like to help shape the future of OpenLitterMap, we would love to have you in our <a href="https://join.slack.com/t/openlittermap/shared_invite/zt-fdctasud-mu~OBQKReRdC9Ai9KgGROw">Slack Channel</a></p>
<p>Every Thursday, 6pm Irish time, we run a community zoom call for ~1 hour where anyone interested in OpenLitterMap can listen in to learn more, and share ideas to help the future direction of the platform.</p>
<p>OpenLitterMap is underdeveloped, but we are a community of over 3,600 contributors who have crowdsourced more than 100,000 uploads from 80 countries.</p>
<p>All of our data is available to explore on the <a href="https://openlittermap.com/global">Global Map</a> and more sophisticated <a href="https://openlittermap.com/world/The%20Netherlands/Zuid-Holland/Wassenaar/map">"city grid maps"</a> are also available to explore. Anyone can download all of our data for free.</p>
<hr>
<p>We have just launched a <a href="https://www.gofundme.com/f/openlittermap-a-revolutionary-app-to-map-litter">GoFundMe</a> which includes our first promotional video and a new demo video showing how to use our app.</p>
<p>The source code for the mobile app (React Native) will launch soon, followed by the OpenLitterAI and our smart contacts.</p>
<p>OpenLitterMap is the first project to reward users with cryptocurrency for the production of geographic information. By using the app and doing "proof of work", users are "mining" Littercoin which we are experimenting with to reward and incentivize the sharing of geospatial data on plastic pollution.</p>
<br>
<p>STAY TUNED FOR LOTS OF EXCITING UPDATES</p>
<hr>
<p>OpenLitterMap-web is built with <a href="https://laravel.com">Laravel</a>, <a href="http://vuejs.org/">Vue.js</a> and <a href="https://bulma.io">Bulma</a></p>
<p>To install this project locally on your machine, download and install <a href="https://laravel.com/docs/5.8/homestead">Homestead</a></p>
<p>First, download <a href="https://www.virtualbox.org/wiki/Downloads">Virtual box</a> which will give you a Virtual Machine. This is used to give us all the same development environment. Alternatively, if you use mac, you can use <a href="https://laravel.com/docs/5.8/valet">Laravel Valet</a></p>
<p>Second, you are going to need to download <a href="https://www.vagrantup.com/downloads.html">Vagrant</a> which you will use to provision, turn on and shut down your VM.</p>
<p>In your root directory, add the vagrant box with</p>  

`vagrant box add laravel/homestead`

then clone the box with `git clone https://github.com/laravel/homestead.git ~/Homestead`

You should now have a "Homestead" folder on your machine at `~/Users/You/Homestead`

<p>Before turning on the VM, we are going to set up the Homestead.yaml file. Every time you save a file, Homestead.yaml will mirror your local code and copy it to the VM which your web-server (VM) will interact with.</p>
<p>Open the Homestead.yaml file, add a new site and create a database.</p>

```
ip: "192.168.10.10"
memory: 2048
cpus: 1
provider: virtualbox

authorize: ~/.ssh/id_rsa.pub

keys:
    - ~/.ssh/id_rsa

folders:
    - map: ~/Code
      to: /home/vagrant/Code

sites:
    - map: olm.test
      to: /home/vagrant/Code/openlittermap-web/public

databases:
    - olm
    - olm_test

features:
    - mysql: true
    - minio: true

buckets:
    - name: olm-public
      policy: public
    - name: olm-public-bbox
      policy: public
```

Next, update your hosts file on your host machine (`sudo nano /etc/hosts` on windows it's `C:\Windows\System32\Drivers\etc\hosts`) and include `192.168.10.10 olm.test`

When you want to boot up the VM, cd into the Homestead folder on your host machine and run `vagrant up`

<p>Download the repo and save it locally into your "Code" folder</p> 

`~/Users/You/Code/openlittermap-web`

If this is your first time installing, you need to run `vagrant provision` 

<p>You also need to install composer and npm dependencies.</p>

Locally, run `npm install`

SSH into the VM with `vagrant ssh`. cd into Code/openlittermap-web, and then run `composer install`
You can migrate and seed the tables with `php artisan migrate --seed`

Once you're done, run `npm run watch` which will build the project into the `public` folder.

You should now be able to open the browser and visit olm.test


If you would like to contribute something, make a new branch locally `git checkout -b feature/my-new-feature`. We would love to see your pull requests!

<p>You might notice there are some websocket errors in the browser. Some operations like adding photos broadcast live events to the client. It's easy to get websockets set up to resolve this.</p>

```
In your .env file, add "WEBSOCKET_BROADCAST_HOST=192.168.10.10"
In broadcasting.php, change 'host' => env('WEBSOCKET_BROADCAST_HOST')
In one window, run `php artisan websockets:serve --host=192.168.10.10`
Then, in another window, run `php artisan horizon`
To test it's working, open another window. Open tinker and run event new(\App\Events\UserSignedUp(1));
```

If you would want to generate some dummy photos for development purposes, you can do so by
using the `php artisan olm:photos:generate-dummy-photos` command to generate 1500 dummy photos. It also takes
arguments so you can do for e.g. `php artisan olm:photos:generate-dummy-photos 2000` and 2000 photos will be generated.
After running the above command, run `php artisan clusters:generate-all` and the photos should be visible in the `Global Map`
tab and in http://olm.test/world/Ireland/County%20Cork/Cork/map

The project uses AWS S3 to store photos on production. On development, however, it uses [Minio](https://laravel.com/docs/8.x/homestead#configuring-minio),
an open source object storage server with an Amazon S3 compatible API. If you copied the .env.example file into .env
you should be able to access the Minio control panel at http://192.168.10.10:9600 (homestead:secretkey).
Remember to update the Access Policy to public for your buckets, on the admin panel.
<p>You are now ready to get started!</p>
<p>Have fun and thanks for taking an interest in OpenLitterMap</p>
