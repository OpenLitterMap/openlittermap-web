<p>Welcome to OpenLitterMap</p>
<p>Arbitary Update </p>
<hr>
<p>Built with Laravel, Vue and Bulma</p>
<br>
<p>To install this project locally on your machine, download and install <a href="https://laravel.com/docs/5.8/homestead">Homestead</a></p>
<br>
<p>First, download <a href="https://www.virtualbox.org/wiki/Downloads">Virtual box</a> which will give you a Virtual Machine. This is used to give us all the same development environment. Alternatively, if you use mac, you can use <a href="https://laravel.com/docs/5.8/valet">Laravel Valet</a></p>
<br>
<p>Second, you are going to need to download <a href="https://www.vagrantup.com/downloads.html">Vagrant</a> which you will use to provision, turn on and shut down your VM.</p>
<br>
<p>Next, add the vagrant box with</p> `vagrant box add laravel/homestead`
<br>
<p>then clone the box with</p> `git clone https://github.com/laravel/homestead.git ~/Homestead`
<br>
<p>- You should now have a "Homestead" folder on your machine at </p> `~/Users/You/Homestead`
<br>
<p>- Before turning on the VM, we are going to set up the Homestead.yaml file. Every time you save a file, Homestead.yaml will mirror your local code and copy it to the VM which your web-server (VM) will interact with.</p>
<br>
<p>- Open the Homestead.yaml file and add a new site and give it a database</p>
<br>

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
    - olmbulma

...
```

<p>You might also want to update your hosts file at /etc/hosts with 192.169.10.10 olm.test</p>

<br>
<p>- When you want to boot up the VM, cd into this folder and run `vagrant up`</p>
<br>
<p>If this is your first time installing, you also need to run `vagrant provision`</p>
<br>
<p>Don't forget to download this repo </p> `~/Users/You/Code/openlittermap-web`
<p>You should now be able to visit</p> `olm.test` <p>in the browser</p>
<br>
<p>You might notice there are some websocket errors in the browser. Also, some functions like adding photos and signing up users broadcast events and it's easier to get websockets set up to resolve this.</p>
<br>
<p>In your .env file, add "WEBSOCKET_BROADCAST_HOST=192.168.10.10"</p>
<p>In broadcasting.php, change 'host' => env('WEBSOCKET_BROADCAST_HOST')</p>
<p>In one window, run `art websockets:serve --host=192.168.10.10`</p>
<p>Then, in another window, run `art horizon`</p>
<p>To test it's working, open another window. Open tinker and run `event new(\App\Events\UserSignedUp(1));`</p>
<br>
<p>Have fun!</p>
