FROM debian:latest

# --- Install PHP ---
RUN apt-get update && \
    apt-get install -y php php-cli php-mbstring php-xml php-curl php-zip php-gd php-json php-common && \
    apt-get install -y python3 python3-pip && \
    apt-get clean

# --- Set working directory ---
WORKDIR /app

# --- Copy all project files ---
COPY . /app

# --- Install Python dependencies ---
RUN pip3 install -r requirements.txt

# --- Expose port ---
EXPOSE 8080

# --- Start PHP server ---
CMD ["php", "-S", "0.0.0.0:8080"]
