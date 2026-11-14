FROM node:22-alpine AS build
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

FROM node:22-alpine
WORKDIR /app
COPY package*.json ./
RUN npm ci --omit=dev
COPY --from=build /app/dist ./dist
ENV PORT=3100
ENV NODE_ENV=production
ENV WHATSAPP_AUTH_DIR=/data/auth
EXPOSE 3100
CMD ["node", "dist/server.js"]
