<img src="https://openlittermap.com/assets/logo_small.jpg" />
<h3>About OpenLitterMap</h3>
<hr>
<p>OpenLitterMap is an open, interactive, and accessible database of the world's litter.</p>
<p>We are building a fun data-collection experience to harness the unprecedented potential of citizen scientists around the world.</p>
<p>We believe that science on pollution should be an open, transparent and democratic process- not limited or controlled by anyone or any group.</p>
<p>If you would like to help shape the future of OpenLitterMap, we would love to have you in our <a href="https://join.slack.com/t/openlittermap/shared_invite/zt-fdctasud-mu~OBQKReRdC9Ai9KgGROw">Slack Channel</a></p>
<p>Every Thursday, 6pm Irish time, we run a community zoom call for ~1 hour where anyone interested in OpenLitterMap can listen in to learn more, and share ideas to help the future direction of the platform.</p>
<p>OpenLitterMap is underdeveloped, but we are a community of over 3,600 contributors who have crowdsourced more than 100,000 uploads from 80 countries.</p>
<p>All of our data is available to explore on the <a href="https://openlittermap.com/global">Global Map</a>, more sophisticated <a href="https://openlittermap.com/world/The%20Netherlands/Zuid-Holland/Wassenaar/map">"city grid maps"</a> are also available to explore. And, anyone can download all of our data for free (Unfortunately this feature is currently broken since we migrated to v2.0 but hopefully we will get it fixed this weekend!)</p>
<hr>
<p>We have just launched a <a href="https://www.gofundme.com/f/openlittermap-a-revolutionary-app-to-map-litter">GoFundMe</a> which includes our first promotional video and a new demo video showing how to use our app</p>
<p>The source code for the mobile app (React Native) will launch soon, followed by the OpenLitterAI and our smart contacts.</p>
<p>OpenLitterMap is the first project in the world that rewards users with cryptocurrency for the production of geographic information. By using the app, and "doing the work", users are "mining" Littercoin which we are experimenting with to reward and incentive the sharing of geospatial data on plastic pollution.</p>
<br>
<p>OpenLitterMap-web is built with <a href="https://laravel.com">Laravel</a>, <a href="http://vuejs.org/">Vue.js</a> and <a href="https://bulma.io">Bulma</a></p>
<p>STAY TUNED FOR LOTS OF EXCITING UPDATES</p>
<hr>
<p>To install this project locally on your machine, download and install <a href="https://laravel.com/docs/5.8/homestead">Homestead</a></p>
<p>First, download <a href="https://www.virtualbox.org/wiki/Downloads">Virtual box</a> which will give you a Virtual Machine. This is used to give us all the same development environment. Alternatively, if you use mac, you can use <a href="https://laravel.com/docs/5.8/valet">Laravel Valet</a></p>
<p>Second, you are going to need to download <a href="https://www.vagrantup.com/downloads.html">Vagrant</a> which you will use to provision, turn on and shut down your VM.</p>
<p>Next, add the vagrant box with</p>  

`vagrant box add laravel/homestead`

<p>then clone the box with</p> 

`git clone https://github.com/laravel/homestead.git ~/Homestead`

<p>- You should now have a "Homestead" folder on your machine at </p> 

`~/Users/You/Homestead`

<p>- Before turning on the VM, we are going to set up the Homestead.yaml file. Every time you save a file, Homestead.yaml will mirror your local code and copy it to the VM which your web-server (VM) will interact with.</p>
<p>- Open the Homestead.yaml file and add a new site and give it a database</p>

```
---
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

...
```

<p>You might also want to update your hosts file at</p>

`/etc/hosts with 192.169.10.10 olm.test`

<p>- When you want to boot up the VM, cd into this folder and run</p>

`vagrant up`

<p>If this is your first time installing, you also need to run</p>

`vagrant provision` 

<p>Download the repo and save it locally </p> 

`~/Users/You/Code/openlittermap-web`

<p>You should now be able to open the browser and visit</p> 

`olm.test`

<p>You might notice there are some websocket errors in the browser. Some functions like adding photos and signing up users broadcast events to the client and it's easy to get websockets set up to resolve this.</p>

```
In your .env file, add "WEBSOCKET_BROADCAST_HOST=192.168.10.10"
In broadcasting.php, change 'host' => env('WEBSOCKET_BROADCAST_HOST')
In one window, run `art websockets:serve --host=192.168.10.10`
Then, in another window, run `art horizon`
To test it's working, open another window. Open tinker and run event new(\App\Events\UserSignedUp(1));
```
<p>Have fun and thanks for taking an interest in OpenLitterMap</p>
