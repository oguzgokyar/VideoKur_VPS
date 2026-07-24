#!/usr/bin/env python3
"""
YouTube Credentials Reset Script
Revoke olunan veya expired token'ları temizler
Bir sonraki scheduler çalıştırıldığında yeni auth isteyecek
"""
import shutil
import sys
from pathlib import Path


def reset_youtube_credentials():
    """YouTube credentials dizinini temizle"""
    base_dir = Path(__file__).parent
    creds_dir = base_dir / 'data' / 'youtube_credentials'
    
    if not creds_dir.exists():
        print(f"✅ Credentials dizini zaten yok: {creds_dir}")
        return True
    
    try:
        print(f"🔄 YouTube credentials temizleniyor...")
        print(f"📁 Dizin: {creds_dir}")
        
        # Backup oluştur (opsiyonel)
        backup_dir = base_dir / 'data' / 'youtube_credentials.backup'
        if creds_dir.exists():
            if backup_dir.exists():
                shutil.rmtree(backup_dir)
            shutil.copytree(creds_dir, backup_dir)
            print(f"💾 Backup oluşturuldu: {backup_dir}")
        
        # Orijinal dizini sil
        if creds_dir.exists():
            shutil.rmtree(creds_dir)
            print(f"✅ Credentials silindi: {creds_dir}")
        
        # Yeni boş dizin oluştur
        creds_dir.mkdir(parents=True, exist_ok=True)
        print(f"📁 Yeni dizin oluşturuldu: {creds_dir}")
        
        print("\n✅ YouTube Credentials sıfırlandı!")
        print("📝 Sonraki scheduler çalıştırıldığında yeni kimlik doğrulama istenecek.")
        print("\n💡 İpucu: Scheduler'ı başlatmak için şunları çalıştırın:")
        print("   python start_production_scheduler.bat")
        
        return True
        
    except Exception as e:
        print(f"❌ Hata: {e}", file=sys.stderr)
        return False


if __name__ == '__main__':
    success = reset_youtube_credentials()
    sys.exit(0 if success else 1)
