# Use Ubuntu for compatibility
FROM ubuntu:22.04

# Install system dependencies
RUN apt-get update && apt-get install -y \
    python3 python3-pip python3-dev \
    php php-cli php-mbstring php-zip php-xml php-curl \
    tesseract-ocr libtesseract-dev \
    mupdf-tools \
    libjpeg-dev zlib1g-dev libpng-dev libfreetype6-dev \
    && apt-get clean

# Set working directory
WORKDIR /app

# Copy project files
COPY . .

# Install Python dependencies
RUN pip3 install --no-cache-dir -r requirements.txt

# Expose port
EXPOSE 8080

# Start PHP server
CMD ["php", "-S", "0.0.0.0:8080"]
