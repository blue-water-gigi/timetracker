FROM node:24.12.0-alpine

ENV NPM_CONFIG_AUDIT=false \
    NPM_CONFIG_FUND=false \
    NPM_CONFIG_UPDATE_NOTIFIER=false

WORKDIR /app

COPY --chmod=755 dev-entrypoint.sh /usr/local/bin/frontend-dev-entrypoint

EXPOSE 5173

ENTRYPOINT ["frontend-dev-entrypoint"]
CMD ["npm", "run", "dev"]
