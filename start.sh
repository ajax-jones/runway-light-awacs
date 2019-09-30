#!/bin/bash
sudo systemctl stop pigpiod.service
sudo pigpiod
python /home/pi/awacs/led_on.py
php /home/pi/awacs/awacs.php
