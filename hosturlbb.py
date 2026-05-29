import os
import telebot
import requests
from urllib.parse import urlparse, unquote

# Bot tokeningizni yozing
BOT_TOKEN = "7766383362:AAG4lly6UymUM4vMhotTOH6Ztz5fvBhCsYg"
bot = telebot.TeleBot(BOT_TOKEN)

# Saytlar bizni bloklamasligi uchun brauzer "niqobi" (Header)
HEADERS = {
    "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
}

@bot.message_handler(commands=['start', 'help'])
def send_welcome(message):
    bot.reply_to(message, "Salom! 🙂 men hohlagan faylingizni yuklab beraman 😉, uning uchun menga kerakli faylni havolasini yuboring🤩 ")

@bot.message_handler(func=lambda message: True)
def handle_link(message):
    url = message.text.strip()
    
    if not (url.startswith("http://") or url.startswith("https://")):
        bot.reply_to(message, "⚠️ Iltimos, to'g'ri havola yuboring!")
        return

    status_message = bot.reply_to(message, "⏳ Havola tekshirilmoqda va yuklanmoqda...")
    
    try:
        # stream=True va allow_redirects=True orqali havola oxirigacha kuzatiladi
        response = requests.get(url, headers=HEADERS, stream=True, allow_redirects=True, timeout=30)
        
        if response.status_code != 200:
            bot.edit_message_text(f"❌ Sayt faylni berishni rad etdi. Xatolik kodi: {response.status_code}", chat_id=message.chat.id, message_id=status_message.message_id)
            return

        # 1. Sayt bergan javob sarlavhasidan (Content-Disposition) fayl nomini qidiramiz
        file_name = None
        cd = response.headers.get('content-disposition')
        if cd and 'filename=' in cd:
            # Sarlavhadan fayl nomini ajratib olish
            for part in cd.split(';'):
                if 'filename=' in part:
                    file_name = part.split('=')[1].strip('"\'')
                    break
        
        # 2. Agar sarlavhada nom bo'lmasa, yakuniy URL manzildan nomini olamiz
        if not file_name:
            final_url = response.url # Redirectdan keyingi yakuniy havola
            parsed_url = urlparse(final_url)
            file_name = os.path.basename(parsed_url.path)
            file_name = unquote(file_name) # Maxsus belgilarni (%20 va h.k.) tozalash

        # 3. Agar baribir nom topilmasa, standart nom beramiz
        if not file_name or '.' not in file_name:
            file_name = "yuklangan_fayl.zip"

        bot.edit_message_text(f"📥 '{file_name}' serverga yuklanmoqda...", chat_id=message.chat.id, message_id=status_message.message_id)

        # Faylni diskka yozish
        with open(file_name, 'wb') as f:
            for chunk in response.iter_content(chunk_size=8192):
                if chunk:
                    f.write(chunk)

        bot.edit_message_text("📤 Fayl Telegram'ga jo'natilmoqda...", chat_id=message.chat.id, message_id=status_message.message_id)

        # Telegramga hujjat sifatda yuborish
        with open(file_name, 'rb') as doc:
            bot.send_document(message.chat.id, doc, reply_to_message_id=message.message_id)
        
        # Vaqtinchalik faylni tozalash
        os.remove(file_name)
        bot.delete_message(message.chat.id, status_message.message_id)

    except Exception as e:
        bot.edit_message_text(f"❌ Xatolik yuz berdi: {str(e)}", chat_id=message.chat.id, message_id=status_message.message_id)
        if 'file_name' in locals() and os.path.exists(file_name):
            os.remove(file_name)

print("Bot qayta ishga tushdi va yanada aqlliroq bo'ldi...")
bot.infinity_polling()