# Playwright Docker Container for ATR
# Node.js environment with Playwright browsers

FROM mcr.microsoft.com/playwright:v1.40.0-jammy

WORKDIR /app

# Copy package files
COPY package.json package-lock.json* ./

# Install dependencies
RUN npm ci --only=production

# Create results directory
RUN mkdir -p /app/results /app/modules

# Set display for headless mode
ENV DISPLAY=:99

# Default command - wait for test execution
CMD ["npx", "playwright", "test", "--help"]
