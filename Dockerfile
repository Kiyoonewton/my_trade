FROM node:lts-bookworm

RUN apt-get update && apt-get install -y \
    libnss3 \
    libnspr4 \
    libx11-xcb-dev \
    libx11-xcb1 \
    libxcb-dri2-0 \
    libxcb-dri3-0 \
    libxcb-glx0 \
    libxcb-present0 \
    libxcb-sync1 \
    libxcb-xfixes0 \
    libxshmfence1 \
    libdbus-1-3 \
    libatk1.0-0 \
    libatk-bridge2.0-0 \
    libdrm2 \
    libxcomposite1 \
    libxdamage1 \
    libxfixes3 \
    libxrandr2 \
    libgbm1 \
    libxkbcommon0 \
    libasound2 \
    libcups2

RUN apt-get update && apt-get install -y xvfb

ENV DISPLAY=:99

RUN Xvfb :99 -screen 0 1024x768x24 &

RUN mkdir -p /root/.cache/puppeteer

RUN npx puppeteer browsers install chrome

WORKDIR /app

COPY package.json ./

ENV YARN_INSTALL_RECOMMENDS false

RUN yarn 

COPY . .

RUN npx tsc --build

EXPOSE 4000

CMD [ "node", "dist/server.js" ]