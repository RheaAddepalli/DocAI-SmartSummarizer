#!/usr/bin/env python
# process_pdf.py

import sys
import io
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')
import json
import os
import re
import hashlib
import fitz  # PyMuPDF
import concurrent.futures
from datetime import datetime
import google.generativeai as genai



import pytesseract
from PIL import Image




# ---------- CONFIG ----------
# Load API key securely from environment variable
# Load .env manually
env_path = os.path.join(os.path.dirname(__file__), ".env")
if os.path.exists(env_path):
    with open(env_path, "r") as f:
        for line in f:
            line = line.strip()
            if "=" in line:
                key, value = line.split("=", 1)
                os.environ[key] = value

API_KEY = os.getenv("GEMINI_API_KEY", "")
genai.configure(api_key=API_KEY)

print("DEBUG: Using GEMINI KEY:", "LOADED" if API_KEY else "NOT FOUND")


MODEL_NAME = "models/gemini-2.5-flash"
MAX_CHUNK_WORDS = 600
DEBUG_LOG = os.path.join(os.path.dirname(__file__), "debug_output.txt")

# Cache folder
CACHE_DIR = os.path.join(os.path.dirname(__file__), "saved_summaries")
os.makedirs(CACHE_DIR, exist_ok=True)
# ----------------------------


def log_debug(name, content):
    try:
        with open(DEBUG_LOG, "a", encoding="utf-8") as f:
            f.write(f"\n--- {name} {datetime.utcnow().isoformat()} UTC ---\n")
            f.write(content + "\n")
    except:
        pass


# -------------------------------------------------------
# PDF HASH (to identify duplicates)
# -------------------------------------------------------
def get_pdf_hash(pdf_path):
    h = hashlib.md5()
    try:
        with open(pdf_path, "rb") as f:
            h.update(f.read())
        return h.hexdigest()
    except:
        return None


def load_cached_summary(pdf_hash):
    cache_path = os.path.join(CACHE_DIR, f"{pdf_hash}.json")
    if os.path.exists(cache_path):
        try:
            with open(cache_path, "r", encoding="utf-8") as f:
                return json.load(f).get("summary")
        except:
            return None
    return None


def save_summary(pdf_hash, summary):
    cache_path = os.path.join(CACHE_DIR, f"{pdf_hash}.json")
    try:
        with open(cache_path, "w", encoding="utf-8") as f:
            json.dump({"summary": summary}, f, ensure_ascii=False, indent=2)
    except:
        pass


# -------------------------------------------------------
# NORMAL TEXT EXTRACTOR (NON-OCR)
# -------------------------------------------------------
def extract_text(pdf_path):
    if not os.path.exists(pdf_path):
        log_debug("missing_file", pdf_path)
        return ""

    pdf_path = os.path.abspath(pdf_path).replace("\\", "/")
    all_pages = []

    try:
        doc = fitz.open(pdf_path)
    except Exception as e:
        log_debug("open_error", str(e))
        return ""

    for page in doc:
        try:
            data = page.get_text("dict")
        except Exception as e:
            log_debug("dict_fail", str(e))
            continue

        parts = []

        for block in data.get("blocks", []):
            if block.get("type") != 0:
                continue

            for line in block.get("lines", []):
                for span in line.get("spans", []):
                    txt = span.get("text", "").strip()
                    if txt:
                        parts.append(txt)

        if parts:
            all_pages.append(" ".join(parts))

    final = re.sub(r"\s+", " ", " ".join(all_pages)).strip()
    return final


# -------------------------------------------------------
# OCR FALLBACK
# -------------------------------------------------------
def ocr_pdf(pdf_path):
    text = ""
    try:
        doc = fitz.open(pdf_path)
        for page in doc:
            pix = page.get_pixmap(dpi=200)
            img = Image.frombytes("RGB", [pix.width, pix.height], pix.samples)
            text += pytesseract.image_to_string(img)
    except Exception as e:
        log_debug("ocr_error", str(e))

    return re.sub(r"\s+", " ", text).strip()


# -------------------------------------------------------
# CHUNKING
# -------------------------------------------------------
def chunk_text_wordwise(text, max_words=MAX_CHUNK_WORDS):
    words = text.split()
    for i in range(0, len(words), max_words):
        yield " ".join(words[i:i + max_words])


# -------------------------------------------------------
# SUMMARIZER
# -------------------------------------------------------
def summarize_chunk(chunk, user_prompt):
    model = genai.GenerativeModel(MODEL_NAME)
    prompt = (
        f"You are a concise technical summarizer. Produce 4–8 bullet points.\n"
        f"USER REQUEST: {user_prompt}\n\n"
        f"TEXT:\n{chunk}\n"
        f"Respond with bullet points only."
    )
    try:
        r = model.generate_content(prompt)
        return r.text.strip() if r.text else ""
    except Exception as e:
        log_debug("chunk_summary_error", str(e))
        return ""


def reduce_summary(partials, user_prompt):
    if len(partials) == 1:
        return partials[0]

    model = genai.GenerativeModel(MODEL_NAME)
    prompt = (
        f"Merge these bullet lists into one clean summary (8–12 bullets).\n"
        f"USER REQUEST: {user_prompt}\n\n" +
        "\n\n".join(partials)
    )

    try:
        r = model.generate_content(prompt)
        if r.text:
            return r.text.strip()
        return "\n".join(partials)
    except Exception as e:
        log_debug("merge_error", str(e))
        return "\n".join(partials)


# -------------------------------------------------------
# CLEANUP FUNCTION (correct placement & alignment)
# -------------------------------------------------------
def clean_llm_output(text):
    """Keep only Gemini's bullets, remove duplicates and clean spacing."""
    lines = text.split("\n")
    cleaned = []

    for line in lines:
        line = line.strip()

        if not line:
            continue

        # Remove AI-intro lines
        low = line.lower()
        if any(bad in low for bad in [
            "here is your summary", "here's a summary",
            "merged into", "clean overview",
            "the summary", "overview", "below is"
        ]):
            continue

        # Normalize bullet "• •" → "•"
        line = line.replace("• •", "•").replace("•  •", "•")

        # If it starts with "*", convert to "•"
        if line.startswith("*"):
            line = "• " + line[1:].strip()

        # If it starts with "-", convert to "•"
        if line.startswith("-"):
            line = "• " + line[1:].strip()

        # If no bullet but meaningful line → add bullet
        if not line.startswith("•"):
            line = "• " + line

        cleaned.append(line)

    return "\n".join(cleaned).strip()

# -------------------------------------------------------
# MAIN SCRIPT
# -------------------------------------------------------
def main():
    if len(sys.argv) < 2:
        print(json.dumps({"summary": "", "error": "Invalid arguments"}))
        return

    args = sys.argv[1:]

    # detect PDF path cleanly
    pdf_parts = []
    for part in args:
        pdf_parts.append(part)
        if part.lower().endswith(".pdf"):
            break

    pdf_path = " ".join(pdf_parts).strip('"')
    prompt_parts = args[len(pdf_parts):]
    user_prompt = " ".join(prompt_parts) if prompt_parts else "Summarize this document."

    # hash for caching
    pdf_hash = get_pdf_hash(pdf_path)

    # check cache
    if pdf_hash:
        cached = load_cached_summary(pdf_hash)
        if cached:
            print(json.dumps({"summary": cached, "cached": True}, ensure_ascii=False))
            return

    # extract normally
    text = extract_text(pdf_path)
    log_debug("NORMAL_TEXT_LENGTH", str(len(text)))

    # if empty -> OCR
    if len(text) < 30:
        log_debug("OCR_TRIGGERED", "Normal extraction empty. Running OCR...")
        text = ocr_pdf(pdf_path)
        log_debug("OCR_TEXT_LENGTH", str(len(text)))

    if len(text) < 30:
        print(json.dumps({"summary": "", "error": "PDF extracted no usable text"}))
        return

    # summarize chunks
    chunks = list(chunk_text_wordwise(text))
    partials = []

    with concurrent.futures.ThreadPoolExecutor(max_workers=6) as ex:
        futures = [ex.submit(summarize_chunk, c, user_prompt) for c in chunks]
        for f in concurrent.futures.as_completed(futures):
            try:
                r = f.result()
                if r:
                    partials.append(r)
            except:
                pass

    if not partials:
        print(json.dumps({"summary": "", "error": "Summaries empty"}))
        return

    final_summary = reduce_summary(partials, user_prompt)

    # clean + beautify
    cleaned = clean_llm_output(final_summary)
    formatted_summary = "Here is your summary:\n\n" + cleaned

    # save cache
    if pdf_hash:
        save_summary(pdf_hash, formatted_summary)

    print(json.dumps({"summary": formatted_summary, "cached": False}, ensure_ascii=False))


if __name__ == "__main__":
    main()
