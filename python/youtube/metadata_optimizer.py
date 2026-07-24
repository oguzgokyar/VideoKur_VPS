"""
YouTube Metadata Optimizer
AI-powered title, description, and tags generation for YouTube Shorts
"""
import json
import re
from typing import Dict, List
from pathlib import Path


class MetadataOptimizer:
    """Generate optimized metadata for YouTube Shorts"""
    
    # YouTube Shorts best practices
    MAX_TITLE_LENGTH = 100
    OPTIMAL_TITLE_LENGTH = 50
    MAX_DESCRIPTION_LENGTH = 5000
    MAX_TAGS = 30
    
    # Common hashtags for Shorts
    SHORTS_HASHTAGS = ['#Shorts', '#YouTubeShorts']
    
    def __init__(self, gemini_key: str = None, model: str = "gemini-2.0-flash"):
        """
        Initialize optimizer
        
        Args:
            gemini_key: Gemini API key for AI generation
            model: Gemini model name
        """
        self.gemini_key = gemini_key
        self.model = model
    
    def optimize_metadata(
        self,
        original_title: str,
        script_text: str,
        tags: List[str] = None,
        use_ai: bool = True
    ) -> Dict:
        """
        Generate optimized metadata for YouTube upload
        
        Args:
            original_title: Original news/video title
            script_text: Video script text
            tags: Optional predefined tags
            use_ai: Use AI to optimize (requires Gemini API key)
            
        Returns:
            Dict with title, description, tags
        """
        if use_ai and self.gemini_key:
            return self._ai_optimize(original_title, script_text, tags)
        else:
            return self._rule_based_optimize(original_title, script_text, tags)
    
    def _ai_optimize(self, title: str, script: str, tags: List[str] = None) -> Dict:
        """Use Gemini AI to generate optimized metadata"""
        try:
            import google.genai as genai
            
            prompt = f"""YouTube Shorts için optimize edilmiş metadata oluştur:

Orijinal Başlık: {title}
Video İçeriği: {script[:500]}

Gereksinimler:
1. Başlık: Max 50 karakter, dikkat çekici, emoji kullan, soru veya merak uyandırıcı
2. Açıklama: 150-300 karakter, özet + CTA (beğen, yorum, abone), hashtag'ler sonda
3. Etiketler: 8-12 adet, Türkçe ve İngilizce karışık, niche + broad

JSON formatında döndür:
{{
    "title": "...",
    "description": "...",
    "tags": ["tag1", "tag2", ...]
}}"""
            
            client = genai.Client(api_key=self.gemini_key)
            response = client.models.generate_content(
                model=self.model,
                contents=prompt
            )
            text = response.text.strip()
            
            # Extract JSON
            json_match = re.search(r'\{[\s\S]*\}', text)
            if json_match:
                result = json.loads(json_match.group())
                
                # Validate and truncate
                result['title'] = self._truncate(result['title'], self.MAX_TITLE_LENGTH)
                result['description'] = self._truncate(result['description'], self.MAX_DESCRIPTION_LENGTH)
                result['tags'] = (result.get('tags', []) or [])[:self.MAX_TAGS]
                
                # Add #Shorts
                if not any('#shorts' in tag.lower() for tag in result['tags']):
                    result['tags'].insert(0, '#Shorts')
                
                return result
            else:
                raise ValueError("AI response is not valid JSON")
                
        except Exception as e:
            print(f"AI optimize hatası: {e}, rule-based kullanılıyor...")
            return self._rule_based_optimize(title, script, tags)
    
    def _rule_based_optimize(self, title: str, script: str, tags: List[str] = None) -> Dict:
        """Rule-based metadata generation"""
        
        # Optimize title
        optimized_title = self._optimize_title(title)
        
        # Generate description
        description = self._generate_description(script, title)
        
        # Generate tags
        if not tags:
            tags = self._generate_tags(title, script)
        
        # Ensure #Shorts is included
        if not any('#shorts' in tag.lower() for tag in tags):
            tags.insert(0, '#Shorts')
        
        return {
            'title': optimized_title,
            'description': description,
            'tags': tags[:self.MAX_TAGS]
        }
    
    def _optimize_title(self, title: str) -> str:
        """Optimize title for YouTube Shorts"""
        # Remove URL artifacts
        title = re.sub(r'\.(html|htm|php)$', '', title, flags=re.IGNORECASE)
        title = re.sub(r'[-_]+', ' ', title)
        
        # Capitalize
        title = title.title()
        
        # Add emoji if not present
        if not self._has_emoji(title):
            # Add relevant emoji based on keywords
            if any(word in title.lower() for word in ['yapay zeka', 'ai', 'robot']):
                title = f"🤖 {title}"
            elif any(word in title.lower() for word in ['teknoloji', 'tech', 'bilim']):
                title = f"⚡ {title}"
            elif any(word in title.lower() for word in ['haber', 'news']):
                title = f"📰 {title}"
            else:
                title = f"🔥 {title}"
        
        # Add question mark or exclamation if impactful
        if '?' not in title and '!' not in title:
            if any(word in title.lower() for word in ['ne', 'nasıl', 'neden', 'kim', 'nerede']):
                title = title.rstrip('.') + '?'
            else:
                title = title.rstrip('.') + '!'
        
        # Truncate if too long
        title = self._truncate(title, self.MAX_TITLE_LENGTH)
        
        return title
    
    def _generate_description(self, script: str, title: str) -> str:
        """Generate description from script"""
        # Get first 200 chars of script as summary
        summary = script[:200].strip()
        if len(script) > 200:
            summary += '...'
        
        # Build description
        description_parts = [
            summary,
            "",
            "🔔 Abone olmayı unutmayın!",
            "💬 Yorumlarınızı bekliyoruz!",
            "👍 Beğenmeyi unutmayın!",
            "",
            "#Shorts #Haber #Teknoloji #Türkçe"
        ]
        
        description = '\n'.join(description_parts)
        
        return self._truncate(description, self.MAX_DESCRIPTION_LENGTH)
    
    def _generate_tags(self, title: str, script: str) -> List[str]:
        """Generate tags from title and script"""
        tags = ['#Shorts', '#YouTubeShorts']
        
        # Extract keywords from title
        title_words = re.findall(r'\w+', title.lower())
        for word in title_words:
            if len(word) > 3:  # Skip short words
                tags.append(word)
                if len(tags) >= 10:
                    break
        
        # Add generic tags
        generic_tags = ['haber', 'teknoloji', 'gündem', 'türkçe', 'news', 'tech']
        for tag in generic_tags:
            if len(tags) < 15:
                tags.append(tag)
        
        return tags[:self.MAX_TAGS]
    
    def _has_emoji(self, text: str) -> bool:
        """Check if text contains emoji"""
        emoji_pattern = re.compile(
            "["
            "\U0001F600-\U0001F64F"  # emoticons
            "\U0001F300-\U0001F5FF"  # symbols & pictographs
            "\U0001F680-\U0001F6FF"  # transport & map symbols
            "\U0001F700-\U0001F77F"  # alchemical symbols
            "\U0001F780-\U0001F7FF"  # Geometric Shapes Extended
            "\U0001F800-\U0001F8FF"  # Supplemental Arrows-C
            "\U0001F900-\U0001F9FF"  # Supplemental Symbols and Pictographs
            "\U0001FA00-\U0001FA6F"  # Chess Symbols
            "\U0001FA70-\U0001FAFF"  # Symbols and Pictographs Extended-A
            "\U00002702-\U000027B0"  # Dingbats
            "]+"
        )
        return bool(emoji_pattern.search(text))
    
    def _truncate(self, text: str, max_length: int) -> str:
        """Truncate text to max length"""
        if not text:
            return ""
        return text[:max_length] if len(text) > max_length else text


def main():
    """CLI test"""
    optimizer = MetadataOptimizer()
    
    title = "yapay-zeka-is-yukunu-azaltacagina-artirdi-mi"
    script = """Yapay zeka iş yükünüzü azaltacağını mı sanıyordunuz? 
    Amazon çalışanları yanıldığımızı söylüyor! Teknoloji dünyasının büyük vaadi şuydu..."""
    
    result = optimizer.optimize_metadata(title, script, use_ai=False)
    
    print("📝 Optimize Edilmiş Metadata:")
    print("=" * 50)
    print(f"\n📌 Başlık: {result['title']}")
    print(f"   Uzunluk: {len(result['title'])} karakter")
    print(f"\n📄 Açıklama:\n{result['description']}")
    print(f"   Uzunluk: {len(result['description'])} karakter")
    print(f"\n🏷️  Etiketler: {', '.join(result['tags'])}")
    print(f"   Toplam: {len(result['tags'])} adet")


if __name__ == '__main__':
    main()
