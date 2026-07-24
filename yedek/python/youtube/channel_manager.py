#!/usr/bin/env python3
"""
YouTube Channel Manager - Unified Multi-Channel Operations

Handles channel and API management for unified youtube_channels.json model.
"""

import json
from pathlib import Path
from typing import Dict, List, Optional
from datetime import datetime


class YouTubeChannelManager:
    """
    Manages YouTube channels and their associated APIs
    """
    
    def __init__(self, data_dir: str):
        """
        Initialize channel manager
        
        Args:
            data_dir: Path to data directory containing youtube_channels.json
        """
        self.data_dir = Path(data_dir)
        self.channels_file = self.data_dir / 'youtube_channels.json'
        self.channels_data = self._load_channels()
    
    def _load_channels(self) -> Dict:
        """Load channels data from file"""
        if not self.channels_file.exists():
            return {'channels': []}
        
        with open(self.channels_file, 'r', encoding='utf-8') as f:
            return json.load(f)
    
    def _save_channels(self):
        """Save channels data to file"""
        with open(self.channels_file, 'w', encoding='utf-8') as f:
            json.dump(self.channels_data, f, indent=2, ensure_ascii=False)
    
    def get_channels(self, active_only: bool = False) -> List[Dict]:
        """
        Get all channels
        
        Args:
            active_only: Only return active channels
            
        Returns:
            List of channel dicts
        """
        channels = self.channels_data.get('channels', [])
        
        if active_only:
            channels = [ch for ch in channels if ch.get('is_active', True)]
        
        return channels
    
    def get_channel_by_id(self, channel_id: str) -> Optional[Dict]:
        """
        Get channel by ID
        
        Args:
            channel_id: Channel ID
            
        Returns:
            Channel dict or None
        """
        for channel in self.channels_data.get('channels', []):
            if channel.get('id') == channel_id:
                return channel
        return None
    
    def get_default_channel(self) -> Optional[Dict]:
        """
        Get default channel
        
        Returns:
            Default channel dict or None
        """
        for channel in self.channels_data.get('channels', []):
            if channel.get('is_default'):
                return channel
        
        # If no default, return first active channel
        active_channels = self.get_channels(active_only=True)
        return active_channels[0] if active_channels else None
    
    def get_channel_apis(
        self, 
        channel_id: str, 
        active_only: bool = True,
        authenticated_only: bool = True
    ) -> List[Dict]:
        """
        Get APIs for a channel
        
        Args:
            channel_id: Channel ID
            active_only: Only return active APIs
            authenticated_only: Only return authenticated APIs
            
        Returns:
            List of API dicts
        """
        channel = self.get_channel_by_id(channel_id)
        if not channel:
            return []
        
        apis = channel.get('apis', [])
        
        if active_only:
            apis = [api for api in apis if api.get('is_active')]
        
        if authenticated_only:
            apis = [api for api in apis if api.get('is_authenticated')]
        
        return apis
    
    def get_api_by_id(self, channel_id: str, api_id: str) -> Optional[Dict]:
        """
        Get API by ID
        
        Args:
            channel_id: Channel ID
            api_id: API ID
            
        Returns:
            API dict or None
        """
        apis = self.get_channel_apis(channel_id, active_only=False, authenticated_only=False)
        
        for api in apis:
            if api.get('api_id') == api_id:
                return api
        
        return None
    
    def get_best_api_for_channel(self, channel_id: str) -> Optional[Dict]:
        """
        Get best API for channel (highest remaining quota)
        
        Args:
            channel_id: Channel ID
            
        Returns:
            Best API dict or None
        """
        apis = self.get_channel_apis(channel_id, active_only=True, authenticated_only=True)
        
        if not apis:
            return None
        
        # Calculate remaining quota for each API
        apis_with_quota = []
        for api in apis:
            quota_used = api.get('quota_used_today', 0)
            quota_limit = api.get('daily_quota', 10000)
            remaining = quota_limit - quota_used
            
            # Minimum quota needed for upload (1600) + safety margin (500)
            if remaining >= 2100:
                apis_with_quota.append({
                    **api,
                    'remaining_quota': remaining
                })
        
        if not apis_with_quota:
            return None
        
        # Sort by remaining quota (highest first)
        apis_with_quota.sort(key=lambda a: a['remaining_quota'], reverse=True)
        
        return apis_with_quota[0]
    
    def update_api_quota(
        self, 
        channel_id: str, 
        api_id: str, 
        quota_used: int
    ) -> bool:
        """
        Update API quota usage
        
        Args:
            channel_id: Channel ID
            api_id: API ID
            quota_used: Quota used amount to add
            
        Returns:
            True if updated, False otherwise
        """
        channel = self.get_channel_by_id(channel_id)
        if not channel:
            return False
        
        # Find API
        for api in channel.get('apis', []):
            if api.get('api_id') == api_id:
                api['quota_used_today'] = api.get('quota_used_today', 0) + quota_used
                api['last_upload'] = datetime.now().isoformat()
                api['upload_count_today'] = api.get('upload_count_today', 0) + 1
                self._save_channels()
                return True
        
        return False
    
    def record_quota_error(self, channel_id: str, api_id: str) -> bool:
        """
        Record quota exceeded error for API
        
        Args:
            channel_id: Channel ID
            api_id: API ID
            
        Returns:
            True if recorded, False otherwise
        """
        channel = self.get_channel_by_id(channel_id)
        if not channel:
            return False
        
        # Find API
        for api in channel.get('apis', []):
            if api.get('api_id') == api_id:
                api['quota_error_at'] = datetime.now().isoformat()
                api['quota_used_today'] = api.get('daily_quota', 10000)  # Mark as full
                self._save_channels()
                return True
        
        return False
    
    def reset_daily_quotas_if_needed(self) -> int:
        """
        Reset daily quotas if last reset was yesterday or earlier
        
        Returns:
            Number of APIs reset
        """
        reset_count = 0
        now = datetime.now()
        
        for channel in self.channels_data.get('channels', []):
            for api in channel.get('apis', []):
                last_reset = api.get('last_reset')
                
                if last_reset:
                    last_reset_dt = datetime.fromisoformat(last_reset.replace('Z', '+00:00'))
                    
                    # If last reset was on a different day, reset
                    if last_reset_dt.date() < now.date():
                        api['quota_used_today'] = 0
                        api['upload_count_today'] = 0
                        api['last_reset'] = now.isoformat()
                        api['quota_error_at'] = None
                        reset_count += 1
        
        if reset_count > 0:
            self._save_channels()
        
        return reset_count
    
    def print_status(self):
        """Print channel status summary"""
        channels = self.get_channels()
        
        print("\n" + "="*60)
        print("📊 YOUTUBE CHANNEL STATUS")
        print("="*60)
        
        for channel in channels:
            channel_title = channel.get('channel_title', 'Unknown')
            channel_id = channel.get('id')
            is_default = channel.get('is_default', False)
            apis = channel.get('apis', [])
            
            default_mark = " ⭐ (Varsayılan)" if is_default else ""
            print(f"\n📺 {channel_title}{default_mark}")
            print(f"   ID: {channel_id}")
            print(f"   APIs: {len(apis)} adet")
            
            total_quota_used = 0
            total_quota_limit = 0
            
            for api in apis:
                api_name = api.get('name', 'N/A')
                is_active = api.get('is_active', False)
                is_auth = api.get('is_authenticated', False)
                quota_used = api.get('quota_used_today', 0)
                quota_limit = api.get('daily_quota', 10000)
                
                total_quota_used += quota_used
                total_quota_limit += quota_limit
                
                status = '🟢' if (is_active and is_auth) else '🔴'
                quota_pct = int((quota_used / quota_limit) * 100) if quota_limit > 0 else 0
                
                print(f"      {status} {api_name}: {quota_used}/{quota_limit} ({quota_pct}%)")
            
            total_pct = int((total_quota_used / total_quota_limit) * 100) if total_quota_limit > 0 else 0
            print(f"   Toplam Kota: {total_quota_used}/{total_quota_limit} ({total_pct}%)")
        
        print("\n" + "="*60)


# Utility functions for backward compatibility
def get_default_channel_id(data_dir: str) -> Optional[str]:
    """Get default channel ID"""
    manager = YouTubeChannelManager(data_dir)
    default_channel = manager.get_default_channel()
    return default_channel['id'] if default_channel else None


def get_channel_by_id(data_dir: str, channel_id: str) -> Optional[Dict]:
    """Get channel by ID"""
    manager = YouTubeChannelManager(data_dir)
    return manager.get_channel_by_id(channel_id)
