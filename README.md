```
__  __            ______               ____                                                     
\ \/ ____  __  __/ ________ _____     / __ \____ ___  __                                        
 \  / __ \/ / / / /   / __ `/ __ \   / /_/ / __ `/ / / /                                        
 / / /_/ / /_/ / /___/ /_/ / / / /  / ____/ /_/ / /_/ /                                         
/_/\____/\__,_/\____/\__,_/_/ /_/  /_/    \__,_/\__, /                                          
    ______              _       __            ______/                                           
   / ________  _____   | |     / ____  ____  / ________  ____ ___  ____ ___  ___  _____________ 
  / /_  / __ \/ ___/   | | /| / / __ \/ __ \/ /   / __ \/ __ `__ \/ __ `__ \/ _ \/ ___/ ___/ _ \
 / __/ / /_/ / /       | |/ |/ / /_/ / /_/ / /___/ /_/ / / / / / / / / / / /  __/ /  / /__/  __/
/_/    \____/_/        |__/|__/\____/\____/\____/\____/_/ /_/ /_/_/ /_/ /_/\___/_/   \___/\___/ 
```
<p style="display:flex;justify-content:center;">
<img src="https://img.shields.io/github/v/tag/NextmediaMa/youcan-pay-for-woocommerce?label=Latest%20version"/>
</p>

# Description

This project aims to provide sellers that are using WP with Woocommerce a full on seamless experience to integrate YouCan Pay in their store.

<p style="background:#ebc443d4;color:black;padding:8px 12px;border-radius: 4px;">
Please note that this readme leans more towards experienced developers than normal consumers.
</p>

# Setup

**Prerequisites:**
- Node / npm
- A Wordpress environment in order to test the plugin
- svn *(only required if you are planing to push code)*

**Steps:**

1. Clone the repository locally:
```bash
git clone https://github.com/NextmediaMa/youcan-pay-for-woocommerce.git && cd youcan-pay-for-woocommerce
```
2. Since the plugin ships with the vendor, there is no need to run `composer install`.
3. Run `npm i`

# Publishing a new version

Wether it's a bug fix, improvement or a new feature, this pipeline should be followed in order
to ensure quality code + making sure the process to take that code to production is seamless.

## Before merging

- Make sure you ran `npm run build` if you modified in 1 of the main `.js` files, this generates a
 minified version of those files that are going to be used in case `SCRIPT_DEBUG` was not 
 present or it was set to false, which is the case most of the times.
- Every PR is to be a release, minor or major, it's up to the contributor and the reviewer.

## Versioning

- Make sure you've updated the version the following:
  - `version` in `package.json`
  - `version` in the header of the plugin, can be found at the phpdoc in `youcan-pay.php`
  - `WC_YOUCAN_PAY_VERSION` in `youcan-pay.php`
  - `Stable tag` & `Version` in `readme.txt`
- Write down a changelog in `changelog.txt` by following the same format as the others.
  - Latest changelog is always on top
- Replace the latest changelog in `readme.txt` by the one you just wrote.
- After merging create a tag in github, this is technically not important, but it'll help us
keep a matching versioning in both github and the plugin repo in Wordpress.

## Deploying

After your PR is merged someone with access can update the remote plugin repository in order
for your changes to take effect, note that this is only available for YouCan Pay engineering team.

### How to deploy

1. Create a directory where our plugin code will live:
```bash
mkdir Subversion && cd Subversion
```
2. Pull the plugin:
```bash
svn co http://svn.wp-plugins.org/youcan-pay-for-woocommerce && cd youcan-pay-for-woocommerce
```
3. Copy your previously created Github tag code into `trunk` dir.
4. Run `svn status`, if there is any file there with a `?` next to it (`?       trunk/delete.me`), 
it means that aren't inversion control yet, to fix that you can add them using:
```bash
svn add trunk/delete.me
```
Or batch add them using:
```bash
svn add trunk/*
```

*Note: It might thrown some warning about how some files are already under version control, you can just ignore those*
5. We now have to create a tag for our new plugin release by running the following:
```bash
svn mkdir tags/0.0.1 && svn copy trunk/* tags/0.0.1
```

*Note: Change `0.0.1` with your version.*

6. Lastly it's time to push our changes to the remote repository, to do so, we run the following:
```bash
svn commit -m "Release 0.0.1"
```

*Note: You'll be asked to authenticate in order to push these changes, so you can't do that
 unless you were a part of the dev team.*