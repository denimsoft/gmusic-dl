# For customization syntax see:
# https://github.com/denimsoft/vagranty

$inventory = <<-'YAML'
---
roles:
  default:
    mount:
      srv: { src: ".", dst: "/srv" }
    tasks:
      system: { disabled: false }
      user: { disabled: false }

tasks:
  system:
    priority: 20
    inline: |
      #!/bin/bash
      set -e -o pipefail
      export DEBIAN_FRONTEND=noninteractive
      apt-get -qqy update
      apt-get -qqy install python-pip python-openssl php7.0 php7.0-curl php7.0-zip php-xdebug
      pip install gmusicapi
      [[ -f /usr/local/bin/composer.phar ]] || curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin
      [[ -f /usr/local/bin/composer ]] || ln -s /usr/local/bin/composer.phar /usr/local/bin/composer
  user:
    priority: 50
    privileged: false
    inline: |
      #!/bin/bash
      set -e -o pipefail
      cd /srv
      composer install

YAML

# bootstrap denimsoft/vagranty

if not File.exists?("#{ENV['HOME']}/.vagrant.d/vendor/denimsoft/vagranty/main.rb")
  require "fileutils"
  FileUtils::mkdir_p "#{ENV['HOME']}/.vagrant.d/vendor/denimsoft/vagranty"
  system "git clone https://github.com/denimsoft/vagranty.git #{ENV['HOME']}/.vagrant.d/vendor/denimsoft/vagranty"
end

PROJECT_PATH = File.dirname(__FILE__)
PROJECT_NAME = File.basename(PROJECT_PATH)

require "#{ENV['HOME']}/.vagrant.d/vendor/denimsoft/vagranty/main.rb"