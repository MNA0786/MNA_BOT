import telebot
import time
import logging
from datetime import datetime

# ==================== CONFIGURATION ====================
BOT_TOKEN = "8315381064:AAGk0FGVGmB8j5SjpBvW3rD3_kQHe_hyOWU"  # @BotFather se naya token lo

OWNER_ID = 1080317415

# ONLY 5 CHANNELS - Source aur Destination same ho sakta hai
SOURCE_CHANNELS = {
    "-1003181705395": "🎬 Main Channel (@EntertainmentTadka786)",
    "-1002337293281": "💾 Backup Channel 2",
    "-1003251791991": "🔒 Private Channel", 
    "-1002831605258": "🎞️ Theater Prints (@threater_print_movies)",
    "-1002964109368": "📦 ET Backup (@ETBackup)"
}

# Destination - Main Channel me hi forward hoga (self-forward)
DESTINATION_CHANNEL = "-1003181705395"

# Agar different destination chahiye toh yahan change karo
# DESTINATION_CHANNEL = "-1003083386043"  # Group me forward karna hai

START_TIME = time.time()
bot = telebot.TeleBot(BOT_TOKEN)
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(message)s')

# ==================== FORWARDING ENGINE ====================
@bot.message_handler(content_types=[
    'text', 'photo', 'video', 'document', 
    'audio', 'voice', 'sticker', 'animation'
])
def auto_forward(message):
    """5 channels se automatically forward karega"""
    try:
        chat_id = str(message.chat.id)
        
        # Check: Sirf 5 specified channels se hi forward kare
        if chat_id not in SOURCE_CHANNELS:
            return
        
        # Check: Naya message hai (bot start time ke baad ka)
        if message.date < START_TIME:
            return
        
        # Get channel name for logging
        channel_name = SOURCE_CHANNELS.get(chat_id, "Unknown Channel")
        
        # Log the incoming message
        log_message = f"📥 FROM: {channel_name} | TYPE: {message.content_type}"
        if message.caption:
            log_message += f" | CAPTION: {message.caption[:50]}..."
        print(log_message)
        
        # Forward logic based on content type
        try:
            if message.content_type == 'text':
                forwarded_text = f"{message.text}\n\n🔹 From: {channel_name}"
                bot.send_message(DESTINATION_CHANNEL, forwarded_text)
            
            elif message.content_type == 'photo':
                new_caption = f"{message.caption or ''}\n\n🔹 From: {channel_name}".strip()
                bot.send_photo(
                    DESTINATION_CHANNEL,
                    message.photo[-1].file_id,
                    caption=new_caption
                )
            
            elif message.content_type == 'video':
                new_caption = f"{message.caption or ''}\n\n🔹 From: {channel_name}".strip()
                bot.send_video(
                    DESTINATION_CHANNEL,
                    message.video.file_id,
                    caption=new_caption,
                    supports_streaming=True
                )
            
            elif message.content_type == 'document':
                new_caption = f"{message.caption or ''}\n\n🔹 From: {channel_name}".strip()
                bot.send_document(
                    DESTINATION_CHANNEL,
                    message.document.file_id,
                    caption=new_caption
                )
            
            elif message.content_type in ['audio', 'voice']:
                new_caption = f"{message.caption or ''}\n\n🔹 From: {channel_name}".strip()
                bot.send_audio(
                    DESTINATION_CHANNEL,
                    message.audio.file_id if message.content_type == 'audio' else message.voice.file_id,
                    caption=new_caption
                )
            
            elif message.content_type == 'sticker':
                bot.send_sticker(DESTINATION_CHANNEL, message.sticker.file_id)
            
            elif message.content_type == 'animation':  # GIFs
                new_caption = f"{message.caption or ''}\n\n🔹 From: {channel_name}".strip()
                bot.send_animation(
                    DESTINATION_CHANNEL,
                    message.animation.file_id,
                    caption=new_caption
                )
            
            print(f"✅ Forwarded to: {SOURCE_CHANNELS.get(DESTINATION_CHANNEL, 'Destination')}")
            
            # Telegram rate limit avoid (0.2 seconds delay)
            time.sleep(0.2)
            
        except Exception as e:
            print(f"❌ Forward Error: {str(e)[:100]}")
            
    except Exception as e:
        print(f"❌ Processing Error: {e}")

# ==================== ADMIN CONTROL PANEL ====================
@bot.message_handler(commands=['start', 'help'])
def show_help(message):
    if message.from_user.id == OWNER_ID:
        help_text = f"""
🤖 **Auto-Forward Bot v2.0**

✅ **Active Channels:** {len(SOURCE_CHANNELS)}
🎯 **Destination:** {SOURCE_CHANNELS.get(DESTINATION_CHANNEL, 'Main Channel')}

📋 **Available Commands:**
/status - Bot ka current status
/channels - List all 5 channels  
/test - Test forward functionality
/uptime - Bot running time

⚙️ **Auto-Forwarding:**
• Photos, Videos, Documents
• Audio, Voice messages
• Text, Stickers, GIFs
• All 5 channels → 1 destination

🔒 **Private Mode:** Only you can control
        """
        bot.reply_to(message, help_text, parse_mode='Markdown')

@bot.message_handler(commands=['status'])
def bot_status(message):
    if message.from_user.id == OWNER_ID:
        uptime = time.time() - START_TIME
        hours = int(uptime // 3600)
        minutes = int((uptime % 3600) // 60)
        
        status_text = f"""
📊 **Bot Status Report**

🕒 **Uptime:** {hours}h {minutes}m
📅 **Started:** {datetime.fromtimestamp(START_TIME).strftime('%d %b %Y, %I:%M %p')}
📤 **Sources:** {len(SOURCE_CHANNELS)} channels
📥 **Destination:** {SOURCE_CHANNELS.get(DESTINATION_CHANNEL, 'Main')}

✅ **Ready to forward from:**
"""
        for idx, (ch_id, ch_name) in enumerate(SOURCE_CHANNELS.items(), 1):
            status_text += f"{idx}. {ch_name}\n"
        
        bot.reply_to(message, status_text)

@bot.message_handler(commands=['channels'])
def list_channels(message):
    if message.from_user.id == OWNER_ID:
        channels_text = "📺 **Active Channels List:**\n\n"
        for idx, (ch_id, ch_name) in enumerate(SOURCE_CHANNELS.items(), 1):
            status = "✅" if ch_id == DESTINATION_CHANNEL else "📤"
            channels_text += f"{status} {idx}. {ch_name}\n   `{ch_id}`\n\n"
        
        bot.reply_to(message, channels_text, parse_mode='Markdown')

@bot.message_handler(commands=['test'])
def test_function(message):
    if message.from_user.id == OWNER_ID:
        try:
            test_msg = f"🧪 **Test Message**\nTime: {datetime.now().strftime('%I:%M %p')}\nStatus: ✅ Bot is working!"
            bot.send_message(DESTINATION_CHANNEL, test_msg, parse_mode='Markdown')
            bot.reply_to(message, "✅ Test message sent successfully!")
        except Exception as e:
            bot.reply_to(message, f"❌ Test failed: {e}")

@bot.message_handler(commands=['uptime'])
def show_uptime(message):
    if message.from_user.id == OWNER_ID:
        uptime = time.time() - START_TIME
        days = int(uptime // 86400)
        hours = int((uptime % 86400) // 3600)
        minutes = int((uptime % 3600) // 60)
        
        uptime_text = f"⏰ **Bot Uptime:** {days}d {hours}h {minutes}m"
        bot.reply_to(message, uptime_text)

# ==================== BOT STARTUP ====================
def display_banner():
    print("""
    ╔══════════════════════════════════════════╗
    ║     🚀 AUTO-FORWARD BOT v2.0             ║
    ║        5 CHANNELS → 1 DESTINATION        ║
    ╚══════════════════════════════════════════╝
    """)
    print(f"👤 Owner ID: {OWNER_ID}")
    print(f"📤 Source Channels: {len(SOURCE_CHANNELS)}")
    print(f"📥 Destination: {SOURCE_CHANNELS.get(DESTINATION_CHANNEL, 'Main Channel')}")
    print(f"⏰ Start Time: {datetime.fromtimestamp(START_TIME).strftime('%Y-%m-%d %H:%M:%S')}")
    print("=" * 50)
    print("🟢 Bot is running... Waiting for messages")
    print("\n📺 Active Channels:")
    for idx, (ch_id, ch_name) in enumerate(SOURCE_CHANNELS.items(), 1):
        print(f"   {idx}. {ch_name}")

# ==================== MAIN EXECUTION ====================
if __name__ == "__main__":
    display_banner()
    
    # Bot ko channels me add karne ka reminder
    print("\n⚠️  REMINDER: Bot ko in 5 channels me ADMIN banao with permissions:")
    print("   - Post Messages ✓")
    print("   - Edit Messages ✓")
    print("   - Delete Messages (optional)")
    
    try:
        bot.polling(none_stop=True, interval=1, timeout=30)
    except KeyboardInterrupt:
        print("\n🛑 Bot stopped by user")
    except Exception as e:
        print(f"\n❌ Bot crashed: {e}")
        print("🔄 Restarting in 10 seconds...")
        time.sleep(10)
