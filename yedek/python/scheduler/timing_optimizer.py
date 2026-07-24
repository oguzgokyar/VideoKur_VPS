"""
Optimal Time Calculator
Calculates best upload times based on strategies
"""
from datetime import datetime, timedelta, timezone
from typing import List, Dict, Optional
import random


class TimingOptimizer:
    """Calculate optimal upload times for YouTube Shorts"""
    
    # Best hours for Turkey timezone (UTC+3)
    # Format: (hour, score) - higher score = better time
    OPTIMAL_HOURS = {
        # Weekday patterns
        'weekday': [
            (9, 60),   # Morning
            (10, 65),
            (11, 70),
            (12, 85),  # Lunch peak
            (13, 90),
            (14, 85),
            (15, 70),
            (16, 75),
            (17, 95),  # Evening peak
            (18, 100), # Best time
            (19, 100),
            (20, 95),
            (21, 85),
            (22, 70),
        ],
        # Weekend patterns
        'weekend': [
            (9, 80),   # Morning
            (10, 85),
            (11, 90),
            (12, 85),
            (13, 80),
            (14, 75),
            (15, 75),
            (16, 80),
            (17, 85),
            (18, 90),
            (19, 95),  # Evening peak
            (20, 100), # Best time
            (21, 100),
            (22, 90),
        ]
    }
    
    def __init__(self, timezone_offset: int = 3):
        """
        Initialize optimizer
        
        Args:
            timezone_offset: Timezone offset from UTC (Turkey = +3)
        """
        self.timezone_offset = timezone_offset
    
    def get_next_optimal_time(
        self,
        after_datetime: Optional[datetime] = None,
        strategy: str = 'smart',
        preferred_hours: List[int] = None
    ) -> datetime:
        """
        Get next optimal upload time
        
        Args:
            after_datetime: Get time after this datetime (default: now)
            strategy: 'smart', 'fixed', or 'random'
            preferred_hours: List of preferred hours (0-23) for 'fixed' strategy
            
        Returns:
            Optimal datetime for upload
        """
        if after_datetime is None:
            after_datetime = datetime.now(timezone.utc)
        
        if strategy == 'smart':
            return self._smart_strategy(after_datetime)
        elif strategy == 'fixed':
            return self._fixed_strategy(after_datetime, preferred_hours or [17, 20])
        elif strategy == 'random':
            return self._random_strategy(after_datetime)
        else:
            return self._smart_strategy(after_datetime)
    
    def get_daily_schedule(
        self,
        date: datetime,
        uploads_per_day: int = 2,
        strategy: str = 'smart',
        preferred_hours: List[int] = None
    ) -> List[datetime]:
        """
        Get optimal times for a full day
        
        Args:
            date: Date to schedule for
            uploads_per_day: Number of uploads per day
            strategy: 'smart', 'fixed', or 'random'
            preferred_hours: Preferred hours for 'fixed' strategy
            
        Returns:
            List of optimal datetimes
        """
        times = []
        current = date.replace(hour=0, minute=0, second=0, microsecond=0)
        
        for i in range(uploads_per_day):
            next_time = self.get_next_optimal_time(
                after_datetime=current,
                strategy=strategy,
                preferred_hours=preferred_hours
            )
            
            # Ensure it's still on the same day
            if next_time.date() == date.date():
                times.append(next_time)
                current = next_time + timedelta(hours=1)  # Space out at least 1 hour
            else:
                break
        
        return times
    
    def _smart_strategy(self, after: datetime) -> datetime:
        """Smart strategy: Choose best times based on day and traffic patterns"""
        # Convert to local time
        local_time = after + timedelta(hours=self.timezone_offset)
        
        # Determine if weekday or weekend
        is_weekend = local_time.weekday() >= 5
        pattern = self.OPTIMAL_HOURS['weekend' if is_weekend else 'weekday']
        
        # Get best hours (score >= 85)
        best_hours = [hour for hour, score in pattern if score >= 85]
        
        # Find next best hour
        current_hour = local_time.hour
        
        # Try to find next best hour today
        for hour in best_hours:
            if hour > current_hour:
                target = local_time.replace(hour=hour, minute=0, second=0, microsecond=0)
                return target - timedelta(hours=self.timezone_offset)  # Convert back to UTC
        
        # No good time today, schedule for tomorrow
        tomorrow = local_time + timedelta(days=1)
        tomorrow_pattern = self.OPTIMAL_HOURS['weekend' if tomorrow.weekday() >= 5 else 'weekday']
        tomorrow_best = [h for h, s in tomorrow_pattern if s >= 85]
        
        target_hour = tomorrow_best[0] if tomorrow_best else 18
        target = tomorrow.replace(hour=target_hour, minute=0, second=0, microsecond=0)
        
        return target - timedelta(hours=self.timezone_offset)  # Convert back to UTC
    
    def _fixed_strategy(self, after: datetime, preferred_hours: List[int]) -> datetime:
        """Fixed strategy: Use predefined hours"""
        local_time = after + timedelta(hours=self.timezone_offset)
        current_hour = local_time.hour
        
        # Sort preferred hours
        preferred_hours = sorted(preferred_hours)
        
        # Find next preferred hour today
        for hour in preferred_hours:
            if hour > current_hour:
                target = local_time.replace(hour=hour, minute=0, second=0, microsecond=0)
                return target - timedelta(hours=self.timezone_offset)
        
        # No preferred hour left today, use first hour tomorrow
        tomorrow = local_time + timedelta(days=1)
        target = tomorrow.replace(hour=preferred_hours[0], minute=0, second=0, microsecond=0)
        
        return target - timedelta(hours=self.timezone_offset)
    
    def _random_strategy(self, after: datetime) -> datetime:
        """Random strategy: Random time within next 24 hours"""
        # Random hours offset (0-24)
        hours_offset = random.uniform(1, 24)
        target = after + timedelta(hours=hours_offset)
        
        # Round to nearest 30 minutes
        minutes = (target.minute // 30) * 30
        target = target.replace(minute=minutes, second=0, microsecond=0)
        
        return target
    
    def format_schedule(self, times: List[datetime]) -> str:
        """Format schedule times for display"""
        local_times = [t + timedelta(hours=self.timezone_offset) for t in times]
        
        lines = []
        for i, lt in enumerate(local_times, 1):
            lines.append(f"{i}. {lt.strftime('%Y-%m-%d %H:%M')} (Local)")
        
        return '\n'.join(lines)


def main():
    """CLI test"""
    optimizer = TimingOptimizer(timezone_offset=3)  # Turkey time
    
    print("⏰ Optimal Timing Calculator")
    print("=" * 50)
    
    now = datetime.now(timezone.utc)
    
    print(f"\nCurrent time (UTC): {now.strftime('%Y-%m-%d %H:%M:%S')}")
    print(f"Current time (Local): {(now + timedelta(hours=3)).strftime('%Y-%m-%d %H:%M:%S')}")
    
    print("\n1️⃣  Smart Strategy (Next optimal time):")
    next_time = optimizer.get_next_optimal_time(strategy='smart')
    local_next = next_time + timedelta(hours=3)
    print(f"   UTC: {next_time.strftime('%Y-%m-%d %H:%M:%S')}")
    print(f"   Local: {local_next.strftime('%Y-%m-%d %H:%M:%S')}")
    
    print("\n2️⃣  Fixed Strategy (17:00, 20:00):")
    next_fixed = optimizer.get_next_optimal_time(strategy='fixed', preferred_hours=[17, 20])
    local_fixed = next_fixed + timedelta(hours=3)
    print(f"   UTC: {next_fixed.strftime('%Y-%m-%d %H:%M:%S')}")
    print(f"   Local: {local_fixed.strftime('%Y-%m-%d %H:%M:%S')}")
    
    print("\n3️⃣  Daily Schedule (2 uploads/day, smart):")
    schedule = optimizer.get_daily_schedule(now, uploads_per_day=2, strategy='smart')
    print(optimizer.format_schedule(schedule))


if __name__ == '__main__':
    main()
