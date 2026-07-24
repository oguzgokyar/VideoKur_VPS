"""
User-Friendly Error Messages for Production Pipeline
Provides clear, actionable error messages for common failures
"""

ERROR_MESSAGES = {
    # API Errors
    '403': {
        'title': 'Gemini Erişim Reddedildi',
        'message': 'Bu API key veya proje için model erişimi reddedildi.',
        'action': 'Ayarlar → API Keys bölümünde bu keyi kaldırın veya farklı proje/key ekleyin.',
        'alternative': 'Not: "API anahtarı geçerli" testi yerine model testini kontrol edin.'
    },
    '503': {
        'title': 'Gemini API Sunucuları Yoğun',
        'message': 'Gemini sunucuları şu an yoğun, bu geçici bir durum.',
        'action': '5-10 dakika bekleyin, ardından "Devam Et" butonuna tıklayın.',
        'alternative': 'Alternatif: Ayarlar → API Keys bölümünden farklı bir API key ekleyebilirsiniz.'
    },
    '429': {
        'title': 'API Key Limiti Doldu',
        'message': 'Bu API key için günlük kullanım limiti doldu.',
        'action': 'Yarın tekrar deneyin veya yeni bir API key ekleyin.',
        'alternative': 'Ayarlar → API Keys bölümünden yeni key ekleyebilirsiniz.'
    },
    '500': {
        'title': 'Gemini API Sunucu Hatası',
        'message': 'Gemini sunucularında geçici bir hata oluştu.',
        'action': '5 dakika bekleyip "Devam Et" butonuna tıklayın.',
        'alternative': ''
    },
    '502': {
        'title': 'Gemini API Bağlantı Hatası',
        'message': 'Gemini sunucularına bağlantı kurulamadı.',
        'action': 'İnternet bağlantınızı kontrol edin, ardından "Devam Et" deneyin.',
        'alternative': ''
    },
    'timeout': {
        'title': 'API Zaman Aşımı',
        'message': 'API 2 dakika içinde cevap vermedi.',
        'action': 'İnternet bağlantınızı kontrol edin, "Devam Et" ile tekrar deneyin.',
        'alternative': 'Sorun devam ederse farklı bir API key deneyin.'
    },
    'quota_exceeded': {
        'title': 'Günlük Limit Aşıldı',
        'message': 'Tüm API key\'lerin günlük kullanım limiti doldu.',
        'action': 'Yarın tekrar deneyin veya yeni API key ekleyin.',
        'alternative': 'Ayarlar → API Keys bölümünden Premium API key ekleyebilirsiniz.'
    },
    'stuck': {
        'title': 'İşlem Takıldı',
        'message': '10 dakikadır bu işlemde ilerleme yok.',
        'action': '"Log Gör" ile detayları inceleyin, ardından "Devam Et" veya "Sil" butonlarını kullanın.',
        'alternative': 'Sorun tekrarlanıyorsa lütfen log dosyalarını inceleyin.'
    },
    'ffmpeg': {
        'title': 'Video İşleme Hatası',
        'message': 'Video oluşturulurken FFmpeg hatası oluştu.',
        'action': 'Proje detaylarından altyazı ve video ayarlarını kontrol edin.',
        'alternative': 'Log dosyalarında detaylı FFmpeg çıktısını görebilirsiniz.'
    },
    'subtitle': {
        'title': 'Altyazı Hatası',
        'message': 'Altyazı dosyası oluşturulamadı veya bozuk.',
        'action': 'Proje detaylarından altyazı ayarlarını kontrol edin.',
        'alternative': 'Ayarlar → Altyazı bölümünden varsayılan ayarları kontrol edebilirsiniz.'
    }
}


def get_user_friendly_error(error_type, details=''):
    """
    Get user-friendly error message for display
    
    Args:
        error_type: Error type (e.g., '503', 'timeout', 'stuck')
        details: Technical error details (optional)
    
    Returns:
        dict with title, message, action, alternative, details
    """
    error_info = ERROR_MESSAGES.get(error_type, {
        'title': 'Bilinmeyen Hata',
        'message': 'Beklenmeyen bir hata oluştu.',
        'action': 'Lütfen "Devam Et" ile tekrar deneyin veya işi silin.',
        'alternative': ''
    })
    
    result = {
        'title': error_info['title'],
        'message': error_info['message'],
        'action': error_info['action'],
        'alternative': error_info.get('alternative', ''),
        'details': details
    }
    
    return result


def format_error_for_job(error_type, details=''):
    """
    Format error message for job status
    
    Returns:
        Formatted string for job.error field
    """
    error_info = get_user_friendly_error(error_type, details)
    
    lines = [
        error_info['title'],
        error_info['message'],
        '',
        f"Yapılması gerekenler: {error_info['action']}"
    ]
    
    if error_info['alternative']:
        lines.append(error_info['alternative'])
    
    if details:
        lines.extend(['', f"Teknik detay: {details}"])
    
    return '\n'.join(lines)


def extract_error_code(exception):
    """
    Extract error code from exception
    
    Args:
        exception: Exception object or string
    
    Returns:
        Error code string (e.g., '503', '429', 'timeout')
    """
    error_str = str(exception)
    
    # Check for HTTP error codes
    if '403' in error_str or 'PERMISSION_DENIED' in error_str:
        return '403'
    elif '503' in error_str or 'UNAVAILABLE' in error_str:
        return '503'
    elif '429' in error_str or 'quota' in error_str.lower():
        return '429'
    elif '500' in error_str:
        return '500'
    elif '502' in error_str or 'Bad Gateway' in error_str:
        return '502'
    elif 'timeout' in error_str.lower():
        return 'timeout'
    elif 'quota' in error_str.lower() and 'exceeded' in error_str.lower():
        return 'quota_exceeded'
    else:
        return 'unknown'


if __name__ == '__main__':
    # Test error messages
    print("Testing error messages:\n")
    
    test_cases = ['503', '429', 'timeout', 'stuck', 'quota_exceeded']
    
    for error_type in test_cases:
        print(f"--- {error_type} ---")
        msg = format_error_for_job(error_type, 'Technical details here')
        print(msg)
        print()
