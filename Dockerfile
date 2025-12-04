# ----------------------------------------------------
# Base Ubuntu image
# ----------------------------------------------------
FROM ubuntu:22.04

# Prevent interactive prompts
ENV DEBIAN_FRONTEND=noninteractive

# ----------------------------------------------------
# Install system dependencies
# ----------------------------------------------------
RUN apt-get update && apt-get install -y \
    python3 python3-pip \
    php php-cli php-mbstring php-xml php-curl \
    nginx \
    tesseract-ocr \
    libgl1 libglib2.0-0 \
    poppler-utils \
    && apt-get clean

# ----------------------------------------------------
# Copy application files
# ----------------------------------------------------
WORKDIR /app
COPY . .

# ----------------------------------------------------
# Install Python dependencies
# ----------------------------------------------------
RUN pip3 install --no-cache-dir -r requirements.txt

# ----------------------------------------------------
# Expose application port for Railway
# ----------------------------------------------------
EXPOSE 8080

# ----------------------------------------------------
# Start PHP server (Railway uses port env var)
# ----------------------------------------------------
CMD php -S 0.0.0.0:$PORT
