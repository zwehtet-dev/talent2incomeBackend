FROM node:18-alpine

# Install dependencies
RUN npm install -g laravel-echo-server

# Create app directory
WORKDIR /app

# Copy configuration
COPY laravel-echo-server.json .

# Expose port
EXPOSE 6001

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD wget --no-verbose --tries=1 --spider http://localhost:6001 || exit 1

# Start the server
CMD ["laravel-echo-server", "start"]