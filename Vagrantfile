Vagrant.configure("2") do |config|
  config.vm.box = "bento/ubuntu-22.04"

    # Manager VM configuration
    config.vm.define "ubuntu" do |ubuntu|
      ubuntu.vm.network "private_network", ip: "192.168.33.10"

      ubuntu.vm.synced_folder "C:/laragon/www/backend", "/home/ubuntu"

      ubuntu.vm.provision "shell", inline: <<-SCRIPT
        # Rename the vagrant user to vagrant
        sudo usermod -l ubuntu vagrant
        sudo groupmod -n ubuntu vagrant
        sudo usermod -d /home/ubuntu -m ubuntu

        # Update sudoers file
        sudo sed -i 's/vagrant ALL=(ALL:ALL) NOPASSWD:ALL/ubuntu ALL=(ALL:ALL) NOPASSWD:ALL/' /etc/sudoers

        # Update profile and bashrc
        echo "cd /vagrant" >> /home/ubuntu/.profile
        echo "cd /vagrant" >> /home/ubuntu/.bashrc
        echo "All good!!"
      SCRIPT

	  # vendor.vm.provision "shell", path: "C:/Users/mspt/provision/provision.ubuntu-docker.sh"
	  # vendor.vm.provision "shell", path: "C:/Users/mspt/provision/provision.ubuntu-mysql.sh"
	  # ubuntu.vm.provision "shell", path: "C:/Users/mspt/provision/provision.ubuntu-node.sh"
	  # vendor.vm.provision "shell", path: "C:/Users/mspt/provision/provision.ubuntu-php8.2.sh"
    end
end

