# Littercoin Open Source Repository

![Littercoin User Journey](/images/littercoin_user_journey.png)

![Littercoin High Level Design](/images/littercoin_design.png)


## The Littercoin Smart Contract 

The Littercoin smart contract can be tested directly on the hosted site [here](https://littercoin-smart-contract.vercel.app/)
or it can be downloaded and follow setup steps below.


## Clone the repository
```
git clone https://github.com/lley154/littercoin.git
```

## Setting up to test the littercoin smart contracts
```
export NEXT_PUBLIC_BLOCKFROST_API_KEY=>>>Your API Key Here <<<
sudo npm install --global yarn
cd littercoin/app
[littercoin/app]$ npm install
[littercoin/app]$ yarn dev
yarn run v1.22.19
$ next dev
ready - started server on 0.0.0.0:3000, url: http://localhost:3000
event - compiled client and server successfully in 1702 ms (173 modules)
```

Now you can access the preview testnet interfaces and testing the smart contracts by
going to the URL http://localhost:3000



## Setting up to re-build the plutus scripts

### Cabal+Nix build

Use the Cabal+Nix build if you want to develop with incremental builds, but also have it automatically download all dependencies.

Set up your machine to build things with `Nix`, following the [Plutus README](https://github.com/input-output-hk/plutus/blob/master/README.adoc) (make sure to set up the binary cache!).

To enter a development environment, simply open a terminal on the project's root and use `nix-shell` to get a bash shell:

```
$ nix-shell
```

and you'll have a working development environment for now and the future whenever you enter this directory.

The build should not take too long if you correctly set up the binary cache. If it starts building GHC, stop and setup the binary cache.

Afterwards, the command `cabal build` from the terminal should work (if `cabal` couldn't resolve the dependencies, run `cabal update` and then `cabal build`).

Also included in the environment is a working [Haskell Language Server](https://github.com/haskell/haskell-language-server) you can integrate with your editor.
See [here](https://github.com/haskell/haskell-language-server#configuring-your-editor) for instructions.

### Run cabal repl to generate the plutus scripts

```
[nix-shell:~/src/littercoin]$ cabal repl
...
[1 of 3] Compiling Littercoin.Types ( src/Littercoin/Types.hs, /home/lawrence/src/littercoin/dist-newstyle/build/x86_64-linux/ghc-8.10.7/littercoin-0.1.0.0/build/Littercoin/Types.o )
[2 of 3] Compiling Littercoin.OnChain ( src/Littercoin/OnChain.hs, /home/lawrence/src/littercoin/dist-newstyle/build/x86_64-linux/ghc-8.10.7/littercoin-0.1.0.0/build/Littercoin/OnChain.o )
[3 of 3] Compiling Littercoin.Deploy ( src/Littercoin/Deploy.hs, /home/lawrence/src/littercoin/dist-newstyle/build/x86_64-linux/ghc-8.10.7/littercoin-0.1.0.0/build/Littercoin/Deploy.o )
Ok, three modules loaded.
Prelude Littercoin.Deploy> main
Prelude Littercoin.Deploy> :q
Leaving GHCi.
```
The new plutus scripts will be created in the littercoin/deploy directory

```
[nix-shell:~/src/littercoin]$ ls -l deploy/*.plutus
-rw-rw-r-- 1 lawrence lawrence 6767 Oct 16 20:32 deploy/lc-minting-policy.plutus
-rw-rw-r-- 1 lawrence lawrence 8539 Oct 16 20:32 deploy/lc-validator.plutus
-rw-rw-r-- 1 lawrence lawrence 5073 Oct 16 20:32 deploy/nft-minting-policy.plutus
-rw-rw-r-- 1 lawrence lawrence 5499 Oct 16 20:32 deploy/thread-token-minting-policy.plutus
```


## Support/Issues/Community

[Slack](https://join.slack.com/t/openlittermap/shared_invite/zt-fdctasud-mu~OBQKReRdC9Ai9KgGROw) is our main medium of communication and collaboration. Power-users, newcomers, developers, a community of over 400 members - we're all there. 
