"""
Content Scheduler

RSS feed'leri periyodik olarak kontrol eden scheduler.
"""

import time
import sys
from datetime import datetime
from pathlib import Path

# Parent directory'yi path'e ekle
sys.path.insert(0, str(Path(__file__).parent.parent))

from content.feed_parser import FeedParser

def main():
    """Ana scheduler loop"""
    print("🔄 Content Scheduler başlatıldı")
    print("="*60)
    print("📡 RSS feed'ler her 30 dakikada kontrol edilecek")
    print("⏸️  Durdurmak için Ctrl+C")
    print("="*60)
    
    parser = FeedParser()
    
    iteration = 0
    
    try:
        while True:
            iteration += 1
            print(f"\n🔄 İterasyon #{iteration} - {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
            print("-"*60)
            
            # RSS feed'leri topla
            new_count = parser.fetch_all_feeds()
            
            if new_count > 0:
                print(f"✅ {new_count} yeni içerik eklendi")
            else:
                print("ℹ️  Yeni içerik bulunamadı")
            
            # 30 dakika bekle
            print(f"\n⏳ Sonraki kontrol: 30 dakika sonra...")
            print("-"*60)
            time.sleep(1800)  # 30 dakika = 1800 saniye
            
    except KeyboardInterrupt:
        print("\n\n⏸️  Scheduler durduruldu")
        print("👋 Güle güle!")
        sys.exit(0)
    except Exception as e:
        print(f"\n❌ Hata: {str(e)}")
        sys.exit(1)

if __name__ == '__main__':
    main()
