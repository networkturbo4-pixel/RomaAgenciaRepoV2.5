<?php
$file = 'c:\xampp\htdocs\CESARMENDOZA\modules\chat\chat.js';
$content = file_get_contents($file);

$searchMsgHtml = "let msgHtml = formatMarkdownAndMedia(escaped).replace(/\\n/g, '<br>');";

$replacement = <<<'EOD'
let msgHtml = formatMarkdownAndMedia(escaped).replace(/\n/g, '<br>');
                
                if (msg.message_type === 'poll' && msg.card_data) {
                    try {
                        const poll = typeof msg.card_data === 'string' ? JSON.parse(msg.card_data) : msg.card_data;
                        let totalVotes = 0;
                        const pollVotes = msg.poll_votes || [];
                        const myVotes = msg.my_votes || [];
                        pollVotes.forEach(v => totalVotes += parseInt(v.count));
                        
                        let pollHtml = `<div class="chat-poll-widget">
                            <div class="poll-question"><i class="ph ph-chart-bar" style="color:var(--primary-color); margin-right:0.25rem;"></i> ${escapeHtml(poll.question)}</div>
                            <div class="poll-options">`;
                            
                        poll.options.forEach((opt, idx) => {
                            const vData = pollVotes.find(v => parseInt(v.option_index) === idx);
                            const vCount = vData ? parseInt(vData.count) : 0;
                            const vPct = totalVotes > 0 ? Math.round((vCount / totalVotes) * 100) : 0;
                            const isMyVote = myVotes.includes(idx) || myVotes.includes(idx.toString());
                            const voters = vData && vData.users ? escapeHtml(vData.users) : '';
                            
                            pollHtml += `
                                <div class="poll-option ${isMyVote ? 'voted' : ''}" onclick="votePoll(${msg.id}, ${idx}, ${poll.multiple ? 'true' : 'false'})">
                                    <div class="poll-opt-progress" style="width:${vPct}%;"></div>
                                    <div class="poll-opt-content">
                                        <div class="poll-opt-text">
                                            <div class="poll-opt-radio ${poll.multiple ? 'multiple' : ''}">
                                                ${isMyVote ? '<i class="ph-bold ph-check"></i>' : ''}
                                            </div>
                                            <span>${escapeHtml(opt)}</span>
                                        </div>
                                        <div class="poll-opt-stats" title="${voters}">
                                            <div class="voters-avatars">
                                                ${vCount > 0 ? `<i class="ph-fill ph-users"></i> ${vCount}` : ''}
                                            </div>
                                            <div class="voters-pct">${vPct}%</div>
                                        </div>
                                    </div>
                                </div>`;
                        });
                            
                        pollHtml += `</div>
                            <div class="poll-footer">${totalVotes} voto${totalVotes !== 1 ? 's' : ''} ${poll.multiple ? '• Múltiples opciones permitidas' : ''}</div>
                        </div>`;
                        msgHtml = pollHtml;
                    } catch (e) {
                        console.error('Error parsing poll', e);
                    }
                } else if (msg.message_type === 'task' && msg.card_data) {
                    try {
                        const taskList = typeof msg.card_data === 'string' ? JSON.parse(msg.card_data) : msg.card_data;
                        let tHtml = `<div class="chat-task-widget">
                            <div class="task-title"><i class="ph ph-check-square" style="color:var(--primary-color); margin-right:0.25rem;"></i> ${escapeHtml(taskList.title)}</div>
                            <div class="task-items">`;
                        
                        let completedCount = 0;
                        taskList.items.forEach((item, idx) => {
                            if (item.done) completedCount++;
                            tHtml += `
                                <div class="task-item ${item.done ? 'done' : ''}" onclick="toggleTask(${msg.id}, ${idx})">
                                    <div class="task-item-checkbox">
                                        ${item.done ? '<i class="ph-bold ph-check"></i>' : ''}
                                    </div>
                                    <div class="task-item-text">${escapeHtml(item.text)}</div>
                                    ${item.user_name ? `<div class="task-item-user" title="Completado por ${escapeHtml(item.user_name)}">${escapeHtml(item.user_name.substring(0, 1))}</div>` : ''}
                                </div>
                            `;
                        });
                        
                        const pct = taskList.items.length > 0 ? Math.round((completedCount / taskList.items.length) * 100) : 0;
                        tHtml += `</div>
                            <div class="task-progress">
                                <div class="task-progress-bar"><div class="task-progress-fill" style="width:${pct}%;"></div></div>
                                <span>${completedCount}/${taskList.items.length} (${pct}%)</span>
                            </div>
                        </div>`;
                        msgHtml = tHtml;
                    } catch (e) {}
                }
EOD;

$content = str_replace($searchMsgHtml, $replacement, $content);
file_put_contents($file, $content);
echo "Restored poll & task JS logic\n";
?>
