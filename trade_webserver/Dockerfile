# Use the official Nginx image from the Docker Hub
FROM nginx:latest

# Copy custom Nginx configuration files
COPY ./conf.d/default.conf /etc/nginx/conf.d/default.conf
COPY ./snippets/fastcgi-php.conf /etc/nginx/snippets/fastcgi-php.conf

# Expose port 80
EXPOSE 80
