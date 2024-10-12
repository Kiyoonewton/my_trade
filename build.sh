#!/bin/bash

# Navigate to the trade_crawler directory
cd ./trade_crawler || { echo "Failed to change to trade_crawler directory"; exit 1; }

# Start the PHP artisan server in the background
php artisan serve &
PHP_PID=$!  # Store the PID of the PHP process

# Wait for a moment to allow the server to start
sleep 3

# Check if the PHP server started successfully
if ps -p $PHP_PID > /dev/null
then
    echo "PHP server is running..."

    # Navigate to the trade_puppeteer directory
    cd ../trade_puppeteer || { echo "Failed to change to trade_puppeteer directory"; exit 1; }

    # Start the Node.js app
    node dist/app
else
    echo "Failed to start PHP server"
    exit 1
fi
